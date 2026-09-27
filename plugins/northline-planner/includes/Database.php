<?php
declare(strict_types=1);
namespace Northline;

final class Database
{
    public const VERSION = '1.0.0';

    public static function table(string $name): string
    {
        global $wpdb;
        if (!in_array($name, ['enquiries', 'outbox', 'uploads', 'bookings', 'rate_limits'], true)) {
            throw new \InvalidArgumentException('Unknown NORTHLINE table.');
        }
        return $wpdb->prefix . 'nl_' . $name;
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $queries = [
            'enquiries' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                public_id varchar(24) NOT NULL,
                token_seed varchar(32) NOT NULL,
                token_hash varchar(64) NOT NULL,
                idempotency_key varchar(36) NOT NULL,
                payload_hash varchar(64) NOT NULL,
                payload longtext NOT NULL,
                estimate longtext NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'new',
                notes text NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                expires_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY idempotency_key (idempotency_key),
                KEY created_at (created_at),
                KEY status (status)",
            'outbox' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                enquiry_id bigint unsigned NOT NULL,
                kind varchar(20) NOT NULL,
                dedupe_key varchar(64) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                attempts int unsigned NOT NULL DEFAULT 0,
                next_attempt datetime NOT NULL,
                locked_until datetime NULL,
                lock_token varchar(32) NULL,
                last_error text NULL,
                accepted_at datetime NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY dedupe_key (dedupe_key),
                KEY due (status,next_attempt),
                KEY enquiry_id (enquiry_id)",
            'uploads' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                enquiry_id bigint unsigned NOT NULL,
                filename varchar(160) NOT NULL,
                mime varchar(40) NOT NULL,
                width int unsigned NOT NULL,
                height int unsigned NOT NULL,
                sha256 varchar(64) NOT NULL,
                data mediumblob NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY enquiry_hash (enquiry_id,sha256)",
            'bookings' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                enquiry_id bigint unsigned NOT NULL,
                slot_utc datetime NOT NULL,
                booking_uid varchar(32) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY enquiry_id (enquiry_id),
                UNIQUE KEY slot_utc (slot_utc)",
            'rate_limits' => "fingerprint varchar(64) NOT NULL,
                hits int unsigned NOT NULL DEFAULT 1,
                expires_at bigint unsigned NOT NULL,
                PRIMARY KEY  (fingerprint),
                KEY expires_at (expires_at)",
        ];
        foreach ($queries as $name => $definition) {
            dbDelta('CREATE TABLE ' . self::table($name) . " ({$definition}) ENGINE=InnoDB {$charset};");
        }
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_northline_enquiries');
        }
        add_role('northline_consultant', 'NORTHLINE Consultant', ['read' => true, 'manage_northline_enquiries' => true]);
        update_option('northline_schema_version', self::VERSION, false);
    }

    public static function transaction(callable $fn): mixed
    {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('The database could not start a transaction.');
        }
        try {
            $result = $fn();
            if ($wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('The database could not commit the operation.');
            }
            return $result;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public static function token(array $row): string
    {
        return hash_hmac('sha256', $row['public_id'] . ':' . $row['token_seed'], wp_salt('auth'));
    }

    public static function enquiry(string $publicId): ?array
    {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{24}$/D', $publicId)) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('enquiries') . ' WHERE public_id = %s', $publicId), ARRAY_A) ?: null;
    }

    public static function byId(int $id): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('enquiries') . ' WHERE id = %d', $id), ARRAY_A) ?: null;
    }

    public static function queue(int $id, string $kind, string $suffix = ''): void
    {
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . self::table('outbox') . ' (enquiry_id,kind,dedupe_key,status,next_attempt,created_at) VALUES (%d,%s,%s,%s,%s,%s)',
            $id, $kind, hash('sha256', "{$id}:{$kind}:{$suffix}"), 'pending', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')
        ));
        if ($result === false) {
            throw new \RuntimeException('Unable to save the email outbox item.');
        }
    }

    public static function save(array $brief, string $key): array
    {
        global $wpdb;
        $hash = Brief::fingerprint($brief);
        $table = self::table('enquiries');
        $previous = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE idempotency_key = %s", $key), ARRAY_A);
        if ($previous) {
            if (!hash_equals($previous['payload_hash'], $hash)) {
                throw new \DomainException('This submission key was already used for a different brief.');
            }
            return $previous;
        }
        try {
            return self::transaction(static function () use ($brief, $key, $hash, $table, $wpdb): array {
                $row = [
                    'public_id' => bin2hex(random_bytes(12)), 'token_seed' => bin2hex(random_bytes(16)),
                    'idempotency_key' => $key, 'payload_hash' => $hash,
                    'payload' => wp_json_encode($brief), 'estimate' => wp_json_encode(Estimate::calculate($brief)),
                    'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS),
                ];
                $row['token_hash'] = hash('sha256', self::token($row));
                if ($wpdb->insert($table, $row) === false) {
                    throw new \RuntimeException('Unable to save the enquiry.');
                }
                $row['id'] = (int) $wpdb->insert_id;
                $row['status'] = 'new';
                self::queue($row['id'], 'receipt');
                self::queue($row['id'], 'staff');
                return $row;
            });
        } catch (\RuntimeException $error) {
            // A concurrent retry may have won the unique-key race. Never create a duplicate.
            $previous = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE idempotency_key = %s", $key), ARRAY_A);
            if ($previous && hash_equals($previous['payload_hash'], $hash)) {
                return $previous;
            }
            if ($previous) {
                throw new \DomainException('This submission key belongs to a different brief.');
            }
            throw $error;
        }
    }

    public static function rateLimit(string $scope, int $maximum, int $seconds = 3600): bool
    {
        global $wpdb;
        // Do not trust client-supplied proxy headers. Configure REMOTE_ADDR at the trusted edge.
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? 'local');
        $bucket = intdiv(time(), $seconds);
        $key = hash_hmac('sha256', "{$scope}:{$remote}:{$bucket}", wp_salt('nonce'));
        $table = self::table('rate_limits');
        $result = $wpdb->query($wpdb->prepare("INSERT INTO {$table} (fingerprint,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1", $key, ($bucket + 2) * $seconds));
        return $result !== false && (int) $wpdb->get_var($wpdb->prepare("SELECT hits FROM {$table} WHERE fingerprint=%s", $key)) <= $maximum;
    }

    public static function delete(int $id): void
    {
        global $wpdb;
        self::transaction(static function () use ($wpdb, $id): void {
            foreach (['uploads', 'outbox', 'bookings'] as $name) {
                if ($wpdb->delete(self::table($name), ['enquiry_id' => $id], ['%d']) === false) {
                    throw new \RuntimeException('Unable to delete related project records.');
                }
            }
            if ($wpdb->delete(self::table('enquiries'), ['id' => $id], ['%d']) === false) {
                throw new \RuntimeException('Unable to delete the enquiry.');
            }
        });
    }

    public static function retention(): void
    {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::table('enquiries') . ' WHERE updated_at < %s LIMIT 100', gmdate('Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS)));
        foreach ($ids as $id) {
            self::delete((int) $id);
        }
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table('rate_limits') . ' WHERE expires_at < %d', time()));
    }

    public static function exporter(array $exporters): array
    {
        $exporters['northline'] = ['exporter_friendly_name' => 'NORTHLINE enquiries', 'callback' => [self::class, 'exportPersonalData']];
        return $exporters;
    }

    public static function eraser(array $erasers): array
    {
        $erasers['northline'] = ['eraser_friendly_name' => 'NORTHLINE enquiries', 'callback' => [self::class, 'erasePersonalData']];
        return $erasers;
    }

    private static function matchingEmail(string $email, int $page): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table('enquiries') . ' WHERE payload LIKE %s ORDER BY id LIMIT 50 OFFSET %d', '%' . $wpdb->esc_like(strtolower($email)) . '%', max(0, $page - 1) * 50), ARRAY_A);
        return array_values(array_filter($rows, static fn ($row): bool => (json_decode($row['payload'], true)['email'] ?? '') === strtolower($email)));
    }

    public static function exportPersonalData(string $email, int $page = 1): array
    {
        $rows = self::matchingEmail($email, $page);
        $data = [];
        foreach ($rows as $row) {
            $fields = [];
            foreach (json_decode($row['payload'], true) as $name => $value) {
                $fields[] = ['name' => $name, 'value' => is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value];
            }
            $fields[] = ['name' => 'Illustrative estimate', 'value' => $row['estimate']];
            $fields[] = ['name' => 'Staff notes', 'value' => (string) $row['notes']];
            $data[] = ['group_id' => 'northline', 'group_label' => 'NORTHLINE enquiries', 'item_id' => 'northline-' . $row['id'], 'data' => $fields];
        }
        return ['data' => $data, 'done' => count($rows) < 50];
    }

    public static function erasePersonalData(string $email, int $page = 1): array
    {
        // Always take the first page while deleting, so shifting offsets cannot skip records.
        $rows = self::matchingEmail($email, 1);
        foreach ($rows as $row) {
            self::delete((int) $row['id']);
        }
        return ['items_removed' => (bool) $rows, 'items_retained' => false, 'messages' => [], 'done' => count($rows) < 50];
    }
}

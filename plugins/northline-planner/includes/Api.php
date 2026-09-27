<?php
declare(strict_types=1);
namespace Northline;

final class Api
{
    public static function routes(): void
    {
        register_rest_route('northline/v1', '/challenge', ['methods' => 'GET', 'callback' => [self::class, 'challenge'], 'permission_callback' => '__return_true']);
        register_rest_route('northline/v1', '/enquiries', ['methods' => 'POST', 'callback' => [self::class, 'submit'], 'permission_callback' => '__return_true']);
        register_rest_route('northline/v1', '/slots', ['methods' => 'GET', 'callback' => [self::class, 'slots'], 'permission_callback' => '__return_true']);
        foreach (['' => ['GET', 'read'], '/references' => ['POST', 'upload'], '/booking' => ['POST', 'book']] as $suffix => [$method, $callback]) {
            register_rest_route('northline/v1', '/brief/(?P<id>[a-f0-9]{24})' . $suffix, ['methods' => $method, 'callback' => [self::class, $callback], 'permission_callback' => [self::class, 'authorized']]);
        }
    }

    public static function response(array $data, int $status = 200): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data, $status);
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('Referrer-Policy', 'no-referrer');
        $response->header('X-Content-Type-Options', 'nosniff');
        return $response;
    }

    private static function error(string $code, string $message, int $status, array $extra = []): \WP_Error
    {
        return new \WP_Error('northline_' . $code, $message, ['status' => $status] + $extra);
    }

    public static function challengeValue(?int $issued = null): string
    {
        $body = ($issued ?? time()) . ':' . bin2hex(random_bytes(16));
        return base64_encode($body . ':' . hash_hmac('sha256', $body, wp_salt('nonce')));
    }

    private static function validChallenge(mixed $value): bool
    {
        if (!is_string($value) || strlen($value) > 256) {
            return false;
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $parts = explode(':', $decoded);
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !preg_match('/^[a-f0-9]{32}$/D', $parts[1])) {
            return false;
        }
        $age = time() - (int) $parts[0];
        return $age >= 2 && $age <= 7200 && hash_equals(hash_hmac('sha256', $parts[0] . ':' . $parts[1], wp_salt('nonce')), $parts[2]);
    }

    public static function challenge(): \WP_REST_Response|\WP_Error
    {
        if (!Database::rateLimit('challenge', 90)) {
            return self::error('rate_limit', 'Too many requests. Please try again later.', 429);
        }
        return self::response(['challenge' => self::challengeValue(), 'rateCard' => Estimate::RATE_CARD]);
    }

    private static function sameOrigin(\WP_REST_Request $request): bool
    {
        $origin = rtrim($request->get_header('origin'), '/');
        if ($origin === '') {
            return true;
        }
        $url = wp_parse_url(home_url('/'));
        $expected = $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '');
        return hash_equals($expected, $origin);
    }

    public static function submit(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!self::sameOrigin($request)) {
            return self::error('origin', 'Please submit from the NORTHLINE website.', 403);
        }
        if (strlen($request->get_body()) > 20000) {
            return self::error('too_large', 'The project brief is too long.', 413);
        }
        $input = $request->get_json_params();
        if (!is_array($input)) {
            return self::error('json', 'A valid project brief is required.', 400);
        }
        if (($input['website'] ?? '') !== '' || !self::validChallenge($input['challenge'] ?? null)) {
            return self::error('challenge', 'Your form session needs refreshing. Your draft has not been cleared.', 422);
        }
        if (!Database::rateLimit('submission', 12)) {
            return self::error('rate_limit', 'Too many submissions from this connection. Your draft is still here; please try later.', 429);
        }
        $key = $request->get_header('idempotency-key');
        if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/Di', $key)) {
            return self::error('key', 'A valid submission identifier is required.', 400);
        }
        if (defined('NORTHLINE_TURNSTILE_SECRET') && NORTHLINE_TURNSTILE_SECRET !== '') {
            $captcha = $input['captcha'] ?? '';
            if (!is_string($captcha) || strlen($captcha) > 2048) {
                return self::error('captcha', 'Please complete the security check.', 422);
            }
            $result = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['timeout' => 8, 'body' => ['secret' => NORTHLINE_TURNSTILE_SECRET, 'response' => $captcha]]);
            if (is_wp_error($result)) {
                return self::error('captcha_unavailable', 'The security check is temporarily unavailable. Your draft is still here.', 503);
            }
            if (empty(json_decode(wp_remote_retrieve_body($result), true)['success'])) {
                return self::error('captcha', 'The security check expired. Please complete it again.', 422);
            }
        }
        try {
            $brief = Brief::validate($input);
            $row = Database::save($brief, strtolower($key));
            return self::response(self::receipt($row) + ['token' => Database::token($row)], 201);
        } catch (ValidationException $error) {
            return self::error('validation', $error->getMessage(), 422, ['fields' => $error->errors]);
        } catch (\DomainException $error) {
            return self::error('conflict', $error->getMessage(), 409);
        } catch (\Throwable $error) {
            error_log('NORTHLINE: enquiry persistence failed; inspect database health.');
            return self::error('save_failed', 'We could not confirm that your brief was saved. Please retry; the submission identifier prevents duplicates.', 503);
        }
    }

    public static function authorized(\WP_REST_Request $request): bool|\WP_Error
    {
        $row = Database::enquiry((string) $request['id']);
        if ($row && current_user_can('manage_northline_enquiries')) {
            return true;
        }
        $authorization = $request->get_header('authorization');
        $valid = preg_match('/^Bearer ([a-f0-9]{64})$/D', $authorization, $matches);
        if (!$row || !$valid || strtotime($row['expires_at'] . ' UTC') < time() || !hash_equals($row['token_hash'], hash('sha256', $matches[1]))) {
            return self::error('private', 'This private brief link is invalid or has expired. Please contact the studio with your reference.', 403);
        }
        return true;
    }

    public static function receipt(array $row): array
    {
        global $wpdb;
        $id = (int) $row['id'];
        $booking = $wpdb->get_row($wpdb->prepare('SELECT slot_utc,booking_uid FROM ' . Database::table('bookings') . ' WHERE enquiry_id=%d', $id), ARRAY_A);
        $email = $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . Database::table('outbox') . ' WHERE enquiry_id=%d AND kind=%s', $id, 'receipt'));
        $references = $wpdb->get_results($wpdb->prepare('SELECT id,filename,width,height FROM ' . Database::table('uploads') . ' WHERE enquiry_id=%d ORDER BY id', $id), ARRAY_A);
        return [
            'publicId' => $row['public_id'], 'reference' => 'NL-' . strtoupper(substr($row['public_id'], 0, 8)),
            'brief' => json_decode($row['payload'], true), 'estimate' => json_decode($row['estimate'], true),
            'createdAt' => $row['created_at'] . 'Z', 'expiresAt' => $row['expires_at'] . 'Z',
            'receiptEmail' => $email ?: 'pending', 'references' => $references,
            'booking' => $booking ? ['slot' => str_replace(' ', 'T', $booking['slot_utc']) . 'Z', 'uid' => $booking['booking_uid']] : null,
            'timezone' => wp_timezone_string(),
        ];
    }

    public static function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $row = Database::enquiry((string) $request['id']);
        return $row ? self::response(self::receipt($row)) : self::error('gone', 'This brief is no longer available.', 410);
    }

    public static function upload(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        global $wpdb;
        if (!Database::rateLimit('reference', 30)) {
            return self::error('rate_limit', 'Too many upload attempts. Your enquiry is saved; please try again later.', 429);
        }
        $files = $request->get_file_params();
        $file = $files['reference'] ?? null;
        if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return self::error('upload', 'The image did not upload successfully. Your enquiry is still saved.', 422);
        }
        if (filesize($file['tmp_name']) > 4 * 1024 * 1024) {
            return self::error('size', 'Choose an image smaller than 4 MB.', 422);
        }
        $info = @getimagesize($file['tmp_name']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || ($info['mime'] ?? '') !== $mime || $info[0] < 20 || $info[1] < 20 || $info[0] * $info[1] > 12000000) {
            return self::error('image', 'Use a valid JPEG, PNG or WebP image, at least 20 × 20 pixels and no more than 12 megapixels. SVG and other files are not accepted.', 422);
        }
        if (!function_exists('imagecreatefromstring')) {
            return self::error('image_service', 'Private image processing is unavailable. Your enquiry is saved; the studio can collect references during consultation.', 503);
        }
        $source = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$source) {
            return self::error('decode', 'This image could not be decoded. Try exporting it as a new JPEG.', 422);
        }
        // Decode and re-encode pixels: strip EXIF/GPS, embedded payloads and user filenames from public storage.
        $scale = min(1, 1800 / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        ob_start();
        imagejpeg($image, null, 88);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($source);
        $row = Database::enquiry((string) $request['id']);
        if (!$row) {
            return self::error('gone', 'This brief is no longer available.', 410);
        }
        $filename = substr(sanitize_file_name(pathinfo((string) $file['name'], PATHINFO_FILENAME)), 0, 120) . '.jpg';
        try {
            $id = Database::transaction(static function () use ($wpdb, $row, $filename, $width, $height, $bytes): int {
                $enquiries = Database::table('enquiries');
                $uploads = Database::table('uploads');
                $locked = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$enquiries} WHERE id=%d FOR UPDATE", $row['id']));
                if (!$locked) {
                    throw new \RuntimeException('Enquiry removed.');
                }
                $hash = hash('sha256', $bytes);
                $duplicate = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$uploads} WHERE enquiry_id=%d AND sha256=%s", $row['id'], $hash));
                if ($duplicate) {
                    return (int) $duplicate;
                }
                if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$uploads} WHERE enquiry_id=%d", $row['id'])) >= 4) {
                    throw new \DomainException('A brief can contain up to four reference images.');
                }
                if ($wpdb->insert($uploads, ['enquiry_id' => $row['id'], 'filename' => $filename, 'mime' => 'image/jpeg', 'width' => $width, 'height' => $height, 'sha256' => $hash, 'data' => $bytes, 'created_at' => gmdate('Y-m-d H:i:s')]) === false) {
                    throw new \RuntimeException('Reference persistence failed.');
                }
                return (int) $wpdb->insert_id;
            });
            return self::response(['id' => $id, 'filename' => $filename, 'width' => $width, 'height' => $height], 201);
        } catch (\DomainException $error) {
            return self::error('references_full', $error->getMessage(), 409);
        } catch (\Throwable $error) {
            return self::error('reference_save', 'Your enquiry is saved, but this reference could not be saved. Please retry this image.', 503);
        }
    }

    public static function availableSlots(): array
    {
        global $wpdb;
        $zone = wp_timezone();
        $now = new \DateTimeImmutable('now', $zone);
        $hours = apply_filters('northline_consultation_hours', [2 => ['10:00', '14:00'], 4 => ['10:00', '14:00']]);
        $taken = $wpdb->get_col($wpdb->prepare('SELECT slot_utc FROM ' . Database::table('bookings') . ' WHERE slot_utc > %s', gmdate('Y-m-d H:i:s')));
        $slots = [];
        for ($day = 1; $day <= 21; $day++) {
            $date = $now->modify('+' . $day . ' days');
            foreach ($hours[(int) $date->format('N')] ?? [] as $hour) {
                if (!is_string($hour) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $hour)) {
                    continue;
                }
                $local = new \DateTimeImmutable($date->format('Y-m-d') . ' ' . $hour, $zone);
                $utc = $local->setTimezone(new \DateTimeZone('UTC'));
                if ($utc->getTimestamp() < time() + DAY_IN_SECONDS || in_array($utc->format('Y-m-d H:i:s'), $taken, true)) {
                    continue;
                }
                $slots[] = ['value' => $utc->format('Y-m-d\TH:i:s\Z'), 'label' => $local->format('D, j M · g:i a T')];
            }
        }
        return $slots;
    }

    public static function slots(): \WP_REST_Response|\WP_Error
    {
        if (!Database::rateLimit('slots', 180)) {
            return self::error('rate_limit', 'Please wait before refreshing appointment availability.', 429);
        }
        return self::response(['slots' => self::availableSlots(), 'timezone' => wp_timezone_string(), 'durationMinutes' => 45]);
    }

    public static function book(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        global $wpdb;
        if (!self::sameOrigin($request) || !Database::rateLimit('booking', 20)) {
            return self::error('booking_limit', 'This appointment request could not be accepted. Please try again later.', 429);
        }
        $input = $request->get_json_params();
        $value = is_array($input) ? ($input['slot'] ?? null) : null;
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00Z$/D', $value)) {
            return self::error('slot', 'Choose an available consultation time.', 422);
        }
        $row = Database::enquiry((string) $request['id']);
        if (!$row) {
            return self::error('gone', 'This brief is no longer available.', 410);
        }
        $slot = str_replace(['T', 'Z'], [' ', ''], $value);
        $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Database::table('bookings') . ' WHERE enquiry_id=%d', $row['id']), ARRAY_A);
        if ($existing) {
            return $existing['slot_utc'] === $slot ? self::response(self::receipt($row)) : self::error('already_booked', 'This brief already has a consultation. Contact the studio to change it.', 409);
        }
        if (!in_array($value, array_column(self::availableSlots(), 'value'), true)) {
            return self::error('slot_taken', 'That time is no longer available. Please choose another; your brief is unchanged.', 409);
        }
        try {
            Database::transaction(static function () use ($wpdb, $row, $slot): void {
                $locked = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Database::table('enquiries') . ' WHERE id=%d FOR UPDATE', $row['id']));
                if (!$locked) {
                    throw new \RuntimeException('Enquiry removed.');
                }
                $uid = bin2hex(random_bytes(16));
                if ($wpdb->insert(Database::table('bookings'), ['enquiry_id' => $row['id'], 'slot_utc' => $slot, 'booking_uid' => $uid, 'created_at' => gmdate('Y-m-d H:i:s')]) === false) {
                    throw new \DomainException('That time was booked while you were choosing. Please select another.');
                }
                if ($wpdb->update(Database::table('enquiries'), ['status' => 'consultation', 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $row['id']]) === false) {
                    throw new \RuntimeException('Unable to update the enquiry.');
                }
                Database::queue((int) $row['id'], 'booking', $uid);
                Database::queue((int) $row['id'], 'booking-staff', $uid);
            });
            return self::response(self::receipt($row), 201);
        } catch (\DomainException $error) {
            return self::error('slot_taken', $error->getMessage(), 409);
        } catch (\Throwable $error) {
            return self::error('booking_failed', 'We could not confirm the appointment. Your project brief remains saved; please retry.', 503);
        }
    }
}

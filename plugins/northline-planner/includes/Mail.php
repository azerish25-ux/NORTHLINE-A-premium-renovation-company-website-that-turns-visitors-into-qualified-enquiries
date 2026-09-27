<?php
declare(strict_types=1);
namespace Northline;

final class Mail
{
    public static function link(array $row): string
    {
        // The bearer credential is in the fragment, not the query or path: it stays out of HTTP referrers and server access logs.
        return home_url('/consultation/') . '#ref=' . rawurlencode($row['public_id']) . '&key=' . rawurlencode(Database::token($row));
    }

    public static function money(array $range): string
    {
        return 'CAD ' . number_format((float) $range[0]) . ' – ' . number_format((float) $range[1]);
    }

    public static function html(array $row, string $kind = 'receipt'): string
    {
        $brief = json_decode($row['payload'], true);
        $estimate = json_decode($row['estimate'], true);
        $reference = 'NL-' . strtoupper(substr($row['public_id'], 0, 8));
        $staff = in_array($kind, ['staff', 'booking-staff'], true);
        $title = str_starts_with($kind, 'booking') ? 'Your consultation is reserved.' : 'A considered beginning.';
        if ($staff) {
            $title = str_starts_with($kind, 'booking') ? 'A consultation has been booked.' : 'A new project brief is ready.';
        }
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($reference) . '</title></head><body style="margin:0;background:#f3f0e9;color:#283229;font:16px/1.65 Arial,sans-serif"><div style="max-width:620px;margin:auto;padding:40px 24px"><p style="font-size:24px;letter-spacing:5px;font-weight:700">NORTHLINE</p><p style="font:12px monospace;letter-spacing:2px">PROJECT BRIEF / ' . esc_html($reference) . '</p><h1 style="font:normal 42px/1.1 Georgia,serif">' . esc_html($title) . '</h1><p>Your brief is saved independently of this email. The figures below are illustrative allowances for a fictional portfolio project, not a quotation.</p><h2 style="font-size:22px">' . esc_html(Brief::TYPES[$brief['type']]) . '</h2>';
        $fields = [
            'Client' => $brief['name'], 'Email' => $brief['email'], 'Phone' => $brief['phone'] ?: 'Not supplied',
            'Location' => $brief['town'], 'Existing property' => number_format($brief['propertySize']) . ' sq ft',
            'Affected area' => number_format($brief['area']) . ' sq ft', 'Finish' => Brief::FINISHES[$brief['finish']],
            'Desired timeline' => Brief::TIMELINES[$brief['timeline']], 'Declared budget' => self::budgetLabel($brief['budget']),
        ];
        $html .= '<table style="border-collapse:collapse;width:100%">';
        foreach ($fields as $label => $value) {
            $html .= '<tr><th scope="row" style="text-align:left;padding:10px 12px 10px 0;border-bottom:1px solid #cbc7bd;font-weight:normal;color:#646c60">' . esc_html($label) . '</th><td style="padding:10px 0;border-bottom:1px solid #cbc7bd">' . esc_html($value) . '</td></tr>';
        }
        $html .= '</table><h3>Your priorities</h3><p style="white-space:pre-wrap">' . esc_html($brief['priorities']) . '</p><h3>Illustrative project allowance</h3><p style="font:normal 32px/1.2 Georgia,serif">' . esc_html(self::money($estimate['total'])) . '</p><table style="width:100%">';
        foreach (['construction' => 'Construction', 'design' => 'Design allowance (10%)', 'contingency' => 'Contingency (15%)'] as $key => $label) {
            $html .= '<tr><th scope="row" style="text-align:left;font-weight:normal">' . esc_html($label) . '</th><td style="text-align:right">' . esc_html(self::money($estimate[$key])) . '</td></tr>';
        }
        $html .= '</table><h3>Assumptions & exclusions</h3><ul>';
        foreach ($estimate['assumptions'] as $assumption) {
            $html .= '<li>' . esc_html($assumption) . '</li>';
        }
        $html .= '</ul>';
        if ($estimate['budgetBelowRange']) {
            $html .= '<p><strong>Your declared budget is below this illustrative range.</strong> A consultation can explore a smaller scope or a phased approach. This is not an automatic rejection.</p>';
        }
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Database::table('bookings') . ' WHERE enquiry_id=%d', $row['id']), ARRAY_A);
        if ($booking) {
            $timestamp = strtotime($booking['slot_utc'] . ' UTC');
            $html .= '<h3>Consultation</h3><p>' . esc_html(wp_date('l, j F Y · g:i a T', $timestamp, wp_timezone())) . '<br>45 minutes · Introductory telephone consultation<br>The studio will use the contact details in your brief.</p>';
        }
        $url = $staff ? admin_url('admin.php?page=northline-enquiries&view=' . (int) $row['id']) : self::link($row);
        $label = $staff ? 'Open saved enquiry' : ($booking ? 'View your brief & consultation' : 'Choose your consultation time');
        $html .= '<p style="margin:32px 0"><a href="' . esc_url($url) . '" style="display:inline-block;background:#283229;color:#f3f0e9;padding:15px 24px;text-decoration:none">' . esc_html($label) . ' ↗</a></p><p style="font-size:13px">Your private brief link expires after 30 days. Anyone holding it can view your brief; do not post it publicly. Uploaded reference images are available only to authorized studio staff.</p><hr style="border:0;border-top:1px solid #cbc7bd"><p style="font-size:12px;color:#646c60">NORTHLINE is a fictional design-and-build practice created for a web-development portfolio. No appointment with a real business is implied. Enquiries are retained for up to 180 days after their last update. Use the website privacy information for deletion requests.</p></div></body></html>';
        return $html;
    }

    public static function budgetLabel(string $key): string
    {
        $range = Brief::BUDGETS[$key] ?? [null, null];
        if ($range[0] === null) {
            return 'Exploring the right budget';
        }
        return $range[1] === null ? 'CAD ' . number_format($range[0]) . '+' : self::money($range);
    }

    public static function process(): void
    {
        global $wpdb;
        $table = Database::table('outbox');
        $now = gmdate('Y-m-d H:i:s');
        $due = "attempts < 5 AND ((status IN ('pending','failed') AND next_attempt <= %s) OR (status='processing' AND locked_until < %s))";
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE {$due} ORDER BY id LIMIT 10", $now, $now));
        foreach ($ids as $id) {
            $lock = bin2hex(random_bytes(16));
            $claimed = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='processing', attempts=attempts+1, lock_token=%s, locked_until=%s WHERE id=%d AND {$due}", $lock, gmdate('Y-m-d H:i:s', time() + 300), $id, $now, $now));
            if ($claimed !== 1) {
                continue;
            }
            $message = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
            $row = Database::byId((int) $message['enquiry_id']);
            if (!$row) {
                $wpdb->delete($table, ['id' => $id]);
                continue;
            }
            $brief = json_decode($row['payload'], true);
            $staff = in_array($message['kind'], ['staff', 'booking-staff'], true);
            $recipient = $staff ? get_option('northline_staff_email', get_option('admin_email')) : $brief['email'];
            $subject = ($staff ? 'NORTHLINE studio · ' : 'Your NORTHLINE project · ') . 'NL-' . strtoupper(substr($row['public_id'], 0, 8));
            $accepted = false;
            try {
                $accepted = is_email($recipient) && wp_mail($recipient, $subject, self::html($row, $message['kind']), ['Content-Type: text/html; charset=UTF-8']);
            } catch (\Throwable $error) {
                $accepted = false;
            }
            $attempts = (int) $message['attempts'];
            $values = $accepted
                ? ['status' => 'accepted', 'accepted_at' => gmdate('Y-m-d H:i:s'), 'last_error' => null, 'locked_until' => null, 'lock_token' => null]
                : ['status' => 'failed', 'last_error' => 'The configured email transport did not accept the message. The enquiry remains saved.', 'next_attempt' => gmdate('Y-m-d H:i:s', time() + min(86400, 300 * (2 ** max(0, $attempts - 1)))), 'locked_until' => null, 'lock_token' => null];
            // Lease ownership prevents a stale worker overwriting a newer attempt's result.
            $wpdb->update($table, $values, ['id' => (int) $id, 'lock_token' => $lock]);
        }
    }
}

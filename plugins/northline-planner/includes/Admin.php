<?php
declare(strict_types=1);
namespace Northline;

final class Admin
{
    public static function menu(): void { add_menu_page('NORTHLINE enquiries', 'NORTHLINE', 'manage_northline_enquiries', 'northline-enquiries', [self::class, 'page'], 'dashicons-clipboard', 26); }
    public static function notice(): void
    {
        if (current_user_can('manage_options') && !get_option('northline_demo_seeded')) echo '<div class="notice notice-info"><p><strong>NORTHLINE is ready.</strong> Open <a href="' . esc_url(admin_url('admin.php?page=northline-enquiries')) . '">NORTHLINE</a> to install the editable fictional pages and case studies. Existing content is preserved.</p></div>';
    }
    private static function form(string $operation, int $id = 0): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="northline_action"><input type="hidden" name="operation" value="' . esc_attr($operation) . '"><input type="hidden" name="id" value="' . $id . '">';
        wp_nonce_field('northline_' . $operation);
    }
    private static function endForm(string $label, string $style = 'secondary'): void { submit_button($label, $style); echo '</form>'; }
    public static function page(): void
    {
        if (!current_user_can('manage_northline_enquiries')) wp_die('Permission denied.', '', ['response' => 403]);
        global $wpdb;
        echo '<div class="wrap nl-admin"><style>.nl-admin{max-width:1240px;color:#283229}.nl-admin h1{font:46px/1.1 Georgia,serif;margin:28px 0}.nl-admin h2{font:28px/1.2 Georgia,serif}.nl-admin .nl-kicker{font:12px monospace;letter-spacing:2px}.nl-admin .nl-panels{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:24px}.nl-admin .nl-panel{padding:28px;background:#fff;border:1px solid #d7d7cc;margin:24px 0}.nl-admin .nl-metrics{display:flex;gap:16px;flex-wrap:wrap}.nl-admin .nl-metric{padding:20px 30px;background:#e9eadd}.nl-admin .nl-metric b{display:block;font:32px Georgia,serif}.nl-admin table{width:100%;border-collapse:collapse}.nl-admin td,.nl-admin th{text-align:left;padding:14px 10px;border-bottom:1px solid #e3e4db;vertical-align:top}.nl-admin textarea{width:100%;min-height:150px}.nl-admin .nl-error{border-left:4px solid #a24c38;padding:14px;background:#fff1e8}.nl-admin .nl-images{display:flex;gap:12px;flex-wrap:wrap}.nl-admin .nl-images img{width:180px;height:140px;object-fit:cover}.nl-admin .nl-images figure{margin:0}.nl-admin input[type=email]{width:100%;max-width:440px}@media(max-width:850px){.nl-admin .nl-panels{grid-template-columns:1fr}.nl-admin .nl-table{overflow:auto}}@media print{#adminmenumain,#wpadminbar,.nl-admin form{display:none}#wpcontent{margin:0}.nl-admin .nl-panels{display:block}}</style><p class="nl-kicker">NORTHLINE / STUDIO DESK</p><h1>Projects begin here.</h1><p>Private briefs, reference images, consultations and email-transport status.</p><p><a href="' . esc_url(home_url('/')) . '">Visit website ↗</a>';
        if (current_user_can('edit_posts')) echo ' · <a href="' . esc_url(admin_url('edit.php?post_type=nl_project')) . '">Edit case studies</a>';
        if (current_user_can('edit_theme_options')) echo ' · <a href="' . esc_url(admin_url('site-editor.php')) . '">Open Site Editor</a>';
        echo '</p>';
        if (isset($_GET['updated'])) echo '<div class="notice notice-success inline"><p>The studio update has been saved.</p></div>';
        $view = isset($_GET['view']) ? absint($_GET['view']) : 0;
        if ($view) {
            $row = Database::byId($view);
            if ($row) self::detail($row); else echo '<p>This enquiry is no longer available.</p>';
            echo '</div>'; return;
        }
        $counts = $wpdb->get_results('SELECT status,COUNT(*) AS n FROM ' . Database::table('enquiries') . ' GROUP BY status', OBJECT_K);
        $failed = (int) $wpdb->get_var("SELECT COUNT(DISTINCT enquiry_id) FROM " . Database::table('outbox') . " WHERE status='failed'");
        echo '<div class="nl-metrics">';
        foreach (['new' => 'New enquiries', 'consultation' => 'Consultations'] as $key => $label) echo '<div class="nl-metric"><b>' . (int) ($counts[$key]->n ?? 0) . '</b>' . esc_html($label) . '</div>';
        echo '<div class="nl-metric"><b>' . $failed . '</b>Enquiries with failed email</div></div><div class="nl-panel"><h2>Enquiry register</h2><p><a href="' . esc_url(admin_url('admin.php?page=northline-enquiries')) . '">All</a>';
        foreach (['new', 'reviewed', 'consultation', 'closed'] as $filter) echo ' / <a href="' . esc_url(admin_url('admin.php?page=northline-enquiries&status=' . $filter)) . '">' . esc_html(ucfirst($filter)) . '</a>';
        echo '</p>';
        $status = isset($_GET['status']) && in_array($_GET['status'], ['new', 'reviewed', 'consultation', 'closed'], true) ? (string) $_GET['status'] : '';
        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $where = $status ? $wpdb->prepare(' WHERE status=%s', $status) : '';
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Database::table('enquiries') . $where . ' ORDER BY created_at DESC,id DESC LIMIT 30 OFFSET %d', ($page - 1) * 30), ARRAY_A);
        if (!$rows) echo '<p>No enquiries in this view. Submit a brief through the planner to see the saved project here.</p>';
        else {
            echo '<div class="nl-table"><table><thead><tr><th>Reference / client</th><th>Project</th><th>Illustrative allowance</th><th>Status</th><th>Received</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $b = json_decode($row['payload'], true); $e = json_decode($row['estimate'], true);
                echo '<tr><td><a href="' . esc_url(admin_url('admin.php?page=northline-enquiries&view=' . (int) $row['id'])) . '"><strong>NL-' . esc_html(strtoupper(substr($row['public_id'], 0, 8))) . '</strong></a><br>' . esc_html($b['name']) . '</td><td>' . esc_html(Brief::TYPES[$b['type']]) . '<br><small>' . esc_html($b['town']) . '</small></td><td>' . esc_html(Mail::money($e['total'])) . ($e['budgetBelowRange'] ? '<br><small>Discuss scope / budget alignment</small>' : '') . '</td><td>' . esc_html(ucfirst($row['status'])) . '</td><td>' . esc_html(wp_date('j M Y', strtotime($row['created_at'] . ' UTC'))) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '<p>';
        if ($page > 1) echo '<a href="' . esc_url(add_query_arg('paged', $page - 1)) . '">← Previous</a> ';
        if (count($rows) === 30) echo '<a href="' . esc_url(add_query_arg('paged', $page + 1)) . '">Next →</a>';
        echo '</p></div>';
        if (current_user_can('manage_options')) {
            echo '<div class="nl-panel"><h2>Studio setup & handover</h2><p>Install eight main pages, two utility pages and six complete fictional case studies. Existing pages and edited content are never overwritten.</p>';
            self::form('seed'); self::endForm('Install missing demo content');
            echo '<hr><h3>Studio notification address</h3>';
            self::form('settings');
            echo '<label for="nl-email">Email for new enquiries and consultation notices</label><p><input id="nl-email" type="email" name="staff_email" required value="' . esc_attr(get_option('northline_staff_email', get_option('admin_email'))) . '"></p>';
            self::endForm('Save studio settings');
            echo '<p><strong>Email:</strong> configure a real SMTP or transactional-mail provider and verify its sending domain. “Transport accepted” does not prove inbox delivery. Failed messages retry up to five attempts; saved briefs are not discarded.</p><p><strong>Appointments:</strong> 45-minute calls, Tuesdays and Thursdays at 10:00 and 14:00 in the WordPress site timezone. Change the timezone under Settings → General. Developers can change hours with <code>northline_consultation_hours</code>. No external calendar synchronization is implied.</p><p><strong>Privacy:</strong> private links expire after 30 days; records are removed after 180 days without an update. WordPress Tools → Export/Erase Personal Data handles brief data. Staff should securely provide any requested reference-image copies before erasure.</p></div>';
        }
        echo '</div>';
    }
    private static function detail(array $row): void
    {
        global $wpdb;
        $id = (int) $row['id']; $b = json_decode($row['payload'], true); $e = json_decode($row['estimate'], true);
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=northline-enquiries')) . '">← All enquiries</a></p><div class="nl-panels"><div><section class="nl-panel"><p class="nl-kicker">NL-' . esc_html(strtoupper(substr($row['public_id'], 0, 8))) . '</p><h2>' . esc_html($b['name']) . ' / ' . esc_html(Brief::TYPES[$b['type']]) . '</h2><table>';
        foreach (['Email' => $b['email'], 'Phone' => $b['phone'] ?: 'Not provided', 'Location' => $b['town'], 'Existing property' => number_format($b['propertySize']) . ' sq ft', 'Affected area' => number_format($b['area']) . ' sq ft', 'Finish' => Brief::FINISHES[$b['finish']], 'Desired timeline' => Brief::TIMELINES[$b['timeline']], 'Declared budget' => Mail::budgetLabel($b['budget'])] as $label => $value) echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        echo '</table><h3>Client priorities</h3><p style="white-space:pre-wrap">' . esc_html($b['priorities']) . '</p><h3>Illustrative allowance</h3><p style="font:32px Georgia,serif">' . esc_html(Mail::money($e['total'])) . '</p>';
        foreach (['construction' => 'Construction', 'design' => 'Design allowance', 'contingency' => 'Contingency'] as $key => $label) echo '<p>' . esc_html($label . ': ' . Mail::money($e[$key])) . '</p>';
        if ($e['budgetBelowRange']) echo '<p class="nl-error">The declared budget is below the illustrative range. Discuss scope or phasing; do not treat this as an automatic rejection.</p>';
        echo '<details><summary>Assumptions & exclusions</summary><ul>';
        foreach ($e['assumptions'] as $assumption) echo '<li>' . esc_html($assumption) . '</li>';
        echo '</ul><p>Version: ' . esc_html($e['version']) . '</p></details></section><section class="nl-panel"><h2>Private reference images</h2><p>Stored outside the public Media Library; available only to authorized staff.</p><div class="nl-images">';
        $uploads = $wpdb->get_results($wpdb->prepare('SELECT id,filename FROM ' . Database::table('uploads') . ' WHERE enquiry_id=%d', $id), ARRAY_A);
        foreach ($uploads as $upload) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=northline_image&id=' . (int) $upload['id']), 'northline_image_' . (int) $upload['id']);
            echo '<figure><a href="' . esc_url($url) . '" target="_blank" rel="noopener"><img src="' . esc_url($url) . '" alt="' . esc_attr($upload['filename']) . '"></a><figcaption>' . esc_html($upload['filename']) . '</figcaption></figure>';
        }
        if (!$uploads) echo '<p>No reference images saved.</p>';
        echo '</div></section></div><aside><section class="nl-panel"><h2>Studio notes</h2>';
        self::form('save', $id);
        echo '<p><label for="nl-status">Enquiry status</label><br><select name="status" id="nl-status">';
        foreach (['new', 'reviewed', 'consultation', 'closed'] as $status) echo '<option value="' . $status . '" ' . selected($row['status'], $status, false) . '>' . esc_html(ucfirst($status)) . '</option>';
        echo '</select></p><label for="nl-notes">Private staff notes</label><textarea id="nl-notes" name="notes" maxlength="5000">' . esc_textarea((string) $row['notes']) . '</textarea>';
        self::endForm('Save enquiry', 'primary');
        echo '</section><section class="nl-panel"><h2>Consultation</h2>';
        $booking = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Database::table('bookings') . ' WHERE enquiry_id=%d', $id), ARRAY_A);
        if ($booking) {
            echo '<p><strong>' . esc_html(wp_date('D, j M Y · g:i a T', strtotime($booking['slot_utc'] . ' UTC'), wp_timezone())) . '</strong><br>45-minute introductory call.</p>';
            self::form('cancel', $id); echo '<label><input type="checkbox" required name="confirm" value="yes"> I have arranged cancellation with the client.</label>'; self::endForm('Cancel appointment and release slot');
        } else echo '<p>No appointment reserved.</p>';
        echo '</section><section class="nl-panel"><h2>Email outbox</h2><p>Saving the brief is independent of sending email.</p>';
        foreach ($wpdb->get_results($wpdb->prepare('SELECT kind,status,attempts,last_error FROM ' . Database::table('outbox') . ' WHERE enquiry_id=%d ORDER BY id', $id), ARRAY_A) as $message) {
            echo '<p><strong>' . esc_html(ucfirst(str_replace('-', ' ', $message['kind']))) . '</strong>: ' . esc_html($message['status'] === 'accepted' ? 'Transport accepted' : ucfirst($message['status'])) . '<br><small>Attempts: ' . (int) $message['attempts'] . '</small></p>';
            if ($message['status'] === 'failed') echo '<p class="nl-error">' . esc_html($message['last_error']) . '</p>';
        }
        self::form('retry', $id); self::endForm('Retry failed messages');
        echo '</section><section class="nl-panel"><h2>Erase this enquiry</h2><p>Removes the brief, notes, images, outbox and appointment. This cannot be undone.</p>';
        self::form('delete', $id); echo '<label><input type="checkbox" required name="confirm" value="yes"> Permanently erase all related records.</label>'; self::endForm('Permanently erase', 'delete');
        echo '</section></aside></div>';
    }
    public static function action(): void
    {
        if (!current_user_can('manage_northline_enquiries')) wp_die('Permission denied.', '', ['response' => 403]);
        $operation = sanitize_key(wp_unslash((string) ($_POST['operation'] ?? '')));
        if (!in_array($operation, ['save', 'retry', 'delete', 'cancel', 'seed', 'settings'], true)) wp_die('Unknown action.', '', ['response' => 400]);
        check_admin_referer('northline_' . $operation);
        $id = absint($_POST['id'] ?? 0);
        global $wpdb;
        try {
            if (in_array($operation, ['seed', 'settings'], true)) {
                if (!current_user_can('manage_options')) wp_die('Administrator permission required.', '', ['response' => 403]);
                if ($operation === 'seed') Content::seed();
                else {
                    $email = sanitize_email(wp_unslash((string) ($_POST['staff_email'] ?? '')));
                    if (!is_email($email)) wp_die('A valid studio email is required.', '', ['response' => 422]);
                    update_option('northline_staff_email', $email, false);
                }
            } else {
                if (!Database::byId($id)) wp_die('Enquiry not found.', '', ['response' => 404]);
                if ($operation === 'save') {
                    $status = sanitize_key(wp_unslash((string) ($_POST['status'] ?? '')));
                    $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? '')));
                    if (!in_array($status, ['new', 'reviewed', 'consultation', 'closed'], true) || strlen($notes) > 20000) wp_die('Invalid status or notes.', '', ['response' => 422]);
                    if ($wpdb->update(Database::table('enquiries'), ['status' => $status, 'notes' => $notes, 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]) === false) throw new \RuntimeException('Unable to save notes.');
                } elseif ($operation === 'retry') {
                    $wpdb->query($wpdb->prepare("UPDATE " . Database::table('outbox') . " SET status='pending',attempts=0,next_attempt=%s,last_error=NULL WHERE enquiry_id=%d AND status='failed'", gmdate('Y-m-d H:i:s'), $id));
                    Mail::process();
                } elseif (($_POST['confirm'] ?? '') !== 'yes') wp_die('Confirmation required.', '', ['response' => 422]);
                elseif ($operation === 'delete') { Database::delete($id); $id = 0; }
                elseif ($operation === 'cancel') Database::transaction(static function () use ($wpdb, $id): void {
                    if ($wpdb->delete(Database::table('bookings'), ['enquiry_id' => $id]) === false || $wpdb->update(Database::table('enquiries'), ['status' => 'reviewed', 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]) === false) throw new \RuntimeException('Unable to cancel the appointment.');
                    $wpdb->query($wpdb->prepare("DELETE FROM " . Database::table('outbox') . " WHERE enquiry_id=%d AND kind IN ('booking','booking-staff') AND status IN ('pending','failed')", $id));
                });
            }
        } catch (\Throwable $error) { wp_die(esc_html($error->getMessage()), 'NORTHLINE could not save this change', ['response' => 503]); }
        wp_safe_redirect(admin_url('admin.php?page=northline-enquiries&updated=1' . ($id ? '&view=' . $id : ''))); exit;
    }
    public static function image(): void
    {
        if (!current_user_can('manage_northline_enquiries')) wp_die('Permission denied.', '', ['response' => 403]);
        $id = absint($_GET['id'] ?? 0); check_admin_referer('northline_image_' . $id);
        global $wpdb;
        $image = $wpdb->get_row($wpdb->prepare('SELECT data FROM ' . Database::table('uploads') . ' WHERE id=%d', $id), ARRAY_A);
        if (!$image) wp_die('Reference not found.', '', ['response' => 404]);
        nocache_headers(); header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff'); header('Content-Type: image/jpeg'); header('Content-Disposition: inline; filename="northline-reference-' . $id . '.jpg"');
        echo $image['data']; exit;
    }
}

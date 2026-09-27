<?php
/** Local development only: capture mail in Mailpit instead of sending it externally. */
declare(strict_types=1);
if (!defined('ABSPATH') || getenv('NORTHLINE_LOCAL_MAIL') !== '1') return;
add_action('phpmailer_init', static function ($mailer): void {
    $mailer->isSMTP();
    $mailer->Host = 'mailpit';
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPSecure = '';
    $mailer->SMTPAutoTLS = false;
    $mailer->Timeout = 8;
    $mailer->setFrom('studio@northline.example', 'NORTHLINE local demonstration', false);
});

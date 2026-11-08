<?php
// iacr/open_webhook.php -- notify an external service when submissions are
// opened (or closed) in Settings > Submissions.
//
// conf/options.php:
//   require_once(__DIR__ . "/../iacr/open_webhook.php");
//   $Opt["hooks"]["settings_saved"][] = "iacr_webhook_settings_saved";
//   $Opt["aicheckUrl"]    = "https://aicheck.iacr.org/api/1.0/";   // POSTs to webhook/submissions under this
//   $Opt["aicheckSecret"] = "...";   // HMAC-SHA256 key shared with the receiver
//
// Requires the core patch that calls the "settings_saved" hook at the end of
// SettingValues::execute() (web settings page, /api/settings, batch/settings.php).
//
// "Open site for submissions" (and "Enable submissions" on a named submission
// class) is a checkbox-date: checking it stores the current request time
// (Conf::$now), and an already-open site keeps its original time. So a round
// whose `open` equals Conf::$now was opened by *this* save.

function iacr_webhook_settings_saved(SettingValues $sv) {
    $conf = $sv->conf;
    $keys = $sv->saved_keys();
    if (!in_array("sub_open", $keys, true) && !in_array("submission_rounds", $keys, true)) {
        return;
    }

    $events = [];
    foreach ($conf->submission_round_list() as $sr) {
        if ($sr->open > 0 && $sr->open === Conf::$now) {
            $events[] = [
                "action" => "opened",
                "submission_class" => $sr->unnamed ? null : $sr->tag,
                "label" => $sr->unnamed ? null : $sr->label,
                "open" => $sr->open,
                "register" => $sr->register ?: null,     // registration deadline, if any
                "submit" => $sr->submit ?: null          // submission deadline, if any
            ];
        }
    }
    if (in_array("sub_open", $keys, true) && ($conf->setting("sub_open") ?? 0) <= 0) {
        $events[] = ["action" => "closed", "submission_class" => null];
    }
    if (empty($events)) {
        return;
    }

    $url = $conf->opt("aicheckUrl");
    $secret = $conf->opt("aicheckSecret");
    if (!$url || !$secret) {
        return;
    }
    $url = rtrim($url, "/") . "/webhook/submissions";
    $body = json_encode([
        "event" => "submissions_status",
        "site" => $conf->opt("paperSite"),
        "conference" => $conf->opt("shortName"),
        "actor_email" => $sv->user->email,
        "time" => Conf::$now,
        "changes" => $events
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    register_shutdown_function(function () use ($url, $secret, $body) {
        if (function_exists("fastcgi_finish_request")) {
            fastcgi_finish_request();
        }
        $ts = (string) time();
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "X-AICheck-Timestamp: {$ts}",
                "X-AICheck-Signature: sha256=" . hash_hmac("sha256", "{$ts}.{$body}", $secret)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code < 200 || $code >= 300) {
            error_log("open_webhook: notify failed: " . (curl_error($ch) ?: "HTTP {$code}"));
        }
        curl_close($ch);
    });
}

<?php
// iacr/settings_webhook.php -- send AICheck the instance's settings whenever
// they are saved, so it never needs a token that may read them. (IACR's review
// bot is an unlisted PC member, and HotCRP lets only administrators and chairs
// read settings.)
//
// conf/options.php:
//   require_once(__DIR__ . "/../iacr/settings_webhook.php");
//   $Opt["hooks"]["settings_saved"][] = "iacr_webhook_settings_push";
//   $Opt["aicheckUrl"]    = "https://aicheck.iacr.org/api/1.0/";   // POSTs to webhook/settings under this
//   $Opt["aicheckSecret"] = "...";     // HMAC-SHA256 key shared with the receiver
//
// Requires the core patch that calls the "settings_saved" hook at the end of
// SettingValues::execute() (web settings page, /api/settings, batch/settings.php),
// as iacr/open_webhook.php does. Both hooks can be registered; each sends its
// own request.
//
// The body is the whole settings object, as GET /api/settings returns it to the
// account that saved them, with the time of the save. AICheck stores it and
// ignores a body older than the settings it already has, so saves that arrive
// out of order do no harm.

function iacr_webhook_settings_push(SettingValues $sv) {
    $conf = $sv->conf;
    $url = $conf->opt("aicheckUrl");
    $secret = $conf->opt("aicheckSecret");
    if (!$url || !$secret || $sv->has_error()) {
        return;
    }
    // A fresh SettingValues reads the settings as now stored, including this save.
    $settings = (new SettingValues($sv->user))->all_jsonv();
    $url = rtrim($url, "/") . "/webhook/settings";
    $body = json_encode([
        "event" => "settings_saved",
        "site" => $conf->opt("paperSite"),
        "conference" => $conf->opt("shortName"),
        "actor_email" => $sv->user->email,
        "time" => Conf::$now,
        "settings" => $settings
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        error_log("settings_webhook: could not encode settings: " . json_last_error_msg());
        return;
    }

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
            CURLOPT_TIMEOUT => 10    // the settings can be a few hundred kilobytes
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code < 200 || $code >= 300) {
            error_log("settings_webhook: notify failed: " . (curl_error($ch) ?: "HTTP {$code}"));
        }
        curl_close($ch);
    });
}

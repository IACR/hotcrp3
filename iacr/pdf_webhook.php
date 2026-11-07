<?php
// iacr/pdf_webhook.php -- notify an external service when a submission PDF
// is uploaded or a submission is submitted. It only notifies the external
// service that the paper has been submitted; the receiver fetches the paper
// itself through the HotCRP REST API.
//
// The following are required in conf/options.php:
//   require_once(__DIR__ . "/../iacr/pdf_webhook.php");
//   $Opt["hooks"]["paper_saved"][] = "pdf_webhook_paper_saved";
//   $Opt["iacrWebhookUrl"]    = "https://aicheck.iacr.org/api/1.0/";   // POSTs to webhook/paper under this
//   $Opt["iacrWebhookSecret"] = "...";     // HMAC-SHA256 key shared with the receiver
//
// Requires the one-line core patch that calls the "paper_saved" hook from
// PaperStatus::log_save_activity().

function pdf_webhook_paper_saved(PaperStatus $ps) {
    $pid = $ps->paperId;
    $conf = $ps->conf;
    if (!$pid || !($url = $conf->opt("iacrWebhookUrl")) || !($secret = $conf->opt("iacrWebhookSecret"))) {
        return;
    }
    $url = rtrim($url, "/") . "/webhook/paper";

    $events = [];
    foreach ($ps->changed_fields() as $f) {
        if ($f->id === DTYPE_SUBMISSION) {
            $events[] = "submission_pdf_uploaded";
        } else if ($f->id === DTYPE_FINAL) {
            $events[] = "final_pdf_uploaded";
        }
    }
    if (($ps->save_status() & PaperStatus::SSF_NEWSUBMIT) !== 0) {
        $events[] = "submission_submitted";      // draft -> ready for review
    }
    if (empty($events)) {
        return;
    }

    // Only identifiers go out; the receiver authenticates to /api with its own
    // token and reads only what that token's scope allows.
    $prow = $ps->saved_prow();
    $docs = [];
    foreach ([DTYPE_SUBMISSION => "submission", DTYPE_FINAL => "final"] as $dt => $key) {
        if (($doc = $prow->document($dt))) {
            $docs[$key] = ["dt" => $dt, "hash" => $doc->text_hash()];
        }
    }
    $body = json_encode([
        "events" => $events,
        "conference" => $conf->opt("shortName"),
        "pid" => $pid,
        "documents" => (object) $docs,
        "submitted" => $prow->timeSubmitted > 0,
        "time" => time()
    ], JSON_UNESCAPED_SLASHES);

    // Send after the response is flushed (under PHP-FPM) so the author never waits.
    register_shutdown_function(function () use ($url, $secret, $body, $pid) {
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
            error_log("pdf_webhook #{$pid}: notify failed: " . (curl_error($ch) ?: "HTTP {$code}"));
        }
        curl_close($ch);
    });
}

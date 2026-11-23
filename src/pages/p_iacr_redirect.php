<?php
// pages/p_iacr_redirect.php -- redirect old /iacr URLs to the IACR pages (/iacr)
// Part of the IACR extensions; not part of upstream HotCRP.
//
// The IACR chair pages used to be scripts under /iacr/ (for example
// /iacr/lncs.php). They are now HotCRP pages named /iacr_* (see
// src/pages/p_iacr.php), and the old scripts are kept in iacr/deprecated/.
// Old URLs are redirected in two ways, depending on how the web server
// routes them:
//
// 1. Servers with an nginx block for /iacr/ run the files in iacr/
//    directly. There, each old HTML page is a one-line stub (for example
//    iacr/lncs.php) that sends a 301 redirect to its new page. Those
//    blocks also serve iacr/api/*, which is not part of this change.
//
// 2. Every other /iacr URL reaches HotCRP's index.php, which treats
//    "iacr" as the page name and the rest as its path, and so ends up
//    here. That includes
//    - "/iacr" with no trailing slash, which an nginx "location .../iacr/"
//      block does not match;
//    - servers with no /iacr/ block at all, such as a development setup.
//    This page sends a 301 redirect to the page that replaced the script
//    named in the path, or "Page not found" if there is none.
//
// The redirect itself is public: it names no data, and the target page
// checks access (chairs and sysadmins only; others are asked to sign in).
// Only GET-style URLs are mapped. The old editor's data scripts
// (getLNCSData.php, saveLNCSData.php) were called only by the old editor
// page, so they are not mapped, and iacr/api/* URLs are never redirected.

class IACR_Redirect_Page {
    /** Old script name (the path under /iacr/) => new HotCRP page.
     * "" covers /iacr and /iacr/.
     * @var array<string,string> */
    const OLD_SCRIPTS = [
        "" => "iacr_home",
        "index.php" => "iacr_home",
        "accepted.php" => "iacr_accepted",
        "downloadaccepted.php" => "iacr_accepted_json",
        "program.php" => "iacr_program",
        "final.php" => "iacr_final",
        "copyright.php" => "iacr_copyrights",
        "lncs.php" => "iacr_lncs",
        "lncsEditor.php" => "iacr_lncs_editor",
        "createFrontMatter.php" => "iacr_lncs_frontmatter",
        "getFrontMatter.php" => "iacr_frontmatter",
        "getSpreadsheet.php" => "iacr_author_emails",
        "getFinalPaperJson.php" => "iacr_final_json"
    ];

    /** Return the page that replaced old /iacr URL path `$path` (the part
     * after "/iacr", such as "/lncs.php"), or null if none did.
     * @param ?string $path
     * @return ?string */
    static function target_page($path) {
        $name = $path ?? "";
        if (str_starts_with($name, "/")) {
            $name = substr($name, 1);
        }
        return self::OLD_SCRIPTS[$name] ?? null;
    }

    static function go(Contact $user, Qrequest $qreq) {
        $page = self::target_page($qreq->path());
        if ($page === null) {
            Multiconference::fail($qreq, 404, ["link" => true], "<0>Page not found");
        }
        throw new Redirection($user->conf->hoturl($page), 301);
    }
}

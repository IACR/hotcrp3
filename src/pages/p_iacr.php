<?php
// pages/p_iacr.php -- shared code for IACR chair pages (/iacr_*)
// Part of the IACR extensions; not part of upstream HotCRP.
//
// This is not a page itself. Each IACR chair page is its own HotCRP page
// (an `iacr_*` entry in etc/pages.json, class IACR_*_Page in
// src/pages/p_iacr_*.php) that uses these helpers for access checks and
// layout. Templates and other shared files live in src/pages/iacr/.
//
// These pages replace the old /iacr/*.php chair scripts, which are kept in
// iacr/deprecated/. Old URLs redirect to the new pages; see
// src/pages/p_iacr_redirect.php for how.

class IACR_Page {
    /** Left-hand menu entries, in order: ID => [LABEL, TARGET, IACRTYPES].
     * TARGET is a HotCRP page name. IACRTYPES, if non-null, lists the `iacrType`
     * values for which the entry appears; a list of "!TYPE" values instead
     * lists the types for which it does not appear. An ID starting with
     * "#" is a section heading.
     * @var array<string,array{string,string,?list<string>}> */
    const MENU = [
        "home" => ["IACR Home", "iacr_home", null],
        "#final" => ["Final versions", "", null],
        "accepted" => ["Accepted papers", "iacr_accepted", null],
        "final" => ["Final papers", "iacr_final", null],
        "copyright" => ["Copyright forms", "iacr_copyrights",
                        ["pkc", "tcc", "crypto", "eurocrypt", "asiacrypt", "tches", "tosc", "cic"]],
        "lncs" => ["LNCS table of contents", "iacr_lncs",
                   ["pkc", "tcc", "crypto", "eurocrypt", "asiacrypt"]],
        "program" => ["Program", "iacr_program", ["!cic"]]
    ];

    /** Chair guideline documents shown at the bottom of the menu.
     * @var array<string,string> */
    const DOCUMENTS = [
        "Program chair guidelines" => "https://www.iacr.org/docs/progchair.pdf",
        "General chair guidelines" => "https://www.iacr.org/docs/genchair.pdf",
        "IACR reviewer guidelines" => "https://www.iacr.org/docs/reviewer.pdf"
    ];

    /** Stop unless `$user` is a chair or sysadmin. Signed-out users are
     * sent to sign in; others get a "Page inaccessible" error. */
    static function check_chair(Contact $user, Qrequest $qreq) {
        if (!$user->privChair) {
            $user->escape($qreq);
        }
    }

    /** Print the theme the user chose in HotCRP, if any, for a page head.
     * This must come before style.css. Like Conf::print_head_tag(), it
     * fixes the order of style.css's light and dark CSS layers; without
     * it, the page follows the browser's color scheme.
     * @see stylesheets/iacr-pages.css */
    static function print_theme_style(Qrequest $qreq) {
        $theme = ($user = $qreq->user()) ? $user->session_theme($qreq) : null;
        if ($theme === "dark") {
            echo "<style id=\"p-theme\">@supports (color: light-dark(red, blue)) { @layer theme, dark-mode; }</style>\n";
        } else if ($theme === "light") {
            echo "<style id=\"p-theme\">@layer dark-mode, theme;</style>\n";
        }
    }

    /** Print the page head, site header, and left-hand menu, and open the
     * main content column. `$active` is the MENU ID to highlight.
     * @param string $title
     * @param string $active */
    static function print_header(Qrequest $qreq, $title, $active) {
        $conf = $qreq->conf();
        $title = htmlspecialchars($title);
        $siteurl = htmlspecialchars($qreq->navigation()->siteurl());
        $home_url = htmlspecialchars($conf->hoturl("index"));
        $iacr_url = htmlspecialchars($conf->hoturl("iacr_home"));
        $short_name = htmlspecialchars($conf->opt("shortName") ?? "");
        include(__DIR__ . "/iacr/header.inc");
        echo '<div class="container-fluid float-start"><div class="row">',
            '<div class="col-4 col-lg-3 col-xl-2">';
        self::print_menu($qreq, $active);
        echo '</div><div class="col-8">';
    }

    /** @param string $active */
    static private function print_menu(Qrequest $qreq, $active) {
        $type = $qreq->conf()->opt("iacrType");
        echo '<div class="leftnav d-flex flex-column p-3">';
        foreach (self::MENU as $id => [$label, $target, $types]) {
            if ($types !== null && !self::type_matches($type, $types)) {
                continue;
            }
            if (str_starts_with($id, "#")) {
                echo '<div class="navheader fw-bold">', htmlspecialchars($label), '</div>';
                continue;
            }
            $klass = "navitem" . ($id === $active ? " active" : "");
            echo '<div><a id="menu', $id, '" class="', $klass, '" href="',
                htmlspecialchars($qreq->conf()->hoturl($target)), '">', htmlspecialchars($label), '</a></div>';
        }
        echo '<div class="navheader fw-bold">IACR Documents</div>';
        foreach (self::DOCUMENTS as $label => $url) {
            echo '<div><a class="navitem" href="', htmlspecialchars($url), '" target="_blank">',
                htmlspecialchars($label), '</a></div>';
        }
        echo '</div>';
    }

    /** @param ?string $type
     * @param list<string> $types
     * @return bool */
    static private function type_matches($type, $types) {
        if (str_starts_with($types[0], "!")) {
            return !in_array("!{$type}", $types, true);
        }
        return in_array($type, $types, true);
    }

    /** Close what print_header() opened and end the document. */
    static function print_footer() {
        echo "</div></div></div>\n</body>\n</html>\n";
    }
}

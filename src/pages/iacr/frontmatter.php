<?php
// src/pages/iacr/frontmatter.php -- LaTeX front matter for LNCS proceedings
// Part of the IACR extensions; not part of upstream HotCRP.
//
// Shared by /iacr_frontmatter (papers in iacr.org order) and
// /iacr_lncs_frontmatter (papers arranged in the LNCS editor). Replaces
// the duplicated code in iacr/getFrontMatter.php and
// iacr/createFrontMatter.php. The output is a draft that the chair edits.
// Only "&" in affiliations is escaped; names and titles are copied as is,
// since titles may contain LaTeX.

class IACR_FrontMatter {
    /** @var Conf */
    protected $conf;

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    /** @param ?string $s
     * @return string */
    static function amp($s) {
        return str_replace("&", "\\&", $s ?? "");
    }

    /** @param string $where
     * @return list<array{firstName:string,lastName:string,affiliation:string,country:?string}> */
    private function people($where) {
        $rows = [];
        $result = $this->conf->qe("select firstName, lastName, affiliation, country from ContactInfo where {$where} order by lastName, firstName");
        while (($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        Dbl::free($result);
        return $rows;
    }

    /** Program chairs: listed PC members with the chair role.
     * @return list<array{firstName:string,lastName:string,affiliation:string,country:?string}> */
    function chairs() {
        return $this->people("(roles&" . Contact::ROLE_CHAIR . ")!=0 and (roles&" . Contact::ROLE_UNLISTEDPC . ")=0");
    }

    /** Program committee: listed PC members who are not chairs.
     * @return list<array{firstName:string,lastName:string,affiliation:string,country:?string}> */
    function committee() {
        return $this->people("(roles&" . Contact::ROLE_PC . ")!=0 and (roles&" . Contact::ROLE_CHAIR . ")=0");
    }

    /** External reviewers: users with no PC role who submitted an external
     * review. People who were asked but never submitted are not listed.
     * @return list<array{firstName:string,lastName:string,affiliation:string,country:?string}> */
    function external_reviewers() {
        return $this->people("(roles&" . Contact::ROLE_PCLIKE . ")=0 and contactId in (select contactId from PaperReview where reviewType=" . REVIEW_EXTERNAL . " and reviewSubmitted>0)");
    }

    /** Print the LaTeX preamble, preface template, and organization pages,
     * up to the table of contents. */
    function print_organization() {
        echo file_get_contents(__DIR__ . "/frontmatter1.txt");
        foreach ($this->chairs() as $p) {
            echo $p["firstName"], " ", $p["lastName"], " & ", self::amp($p["affiliation"]), "\\\\\n";
        }
        echo "\\end{longtable}\n\n",
            "\\section*{Steering Committee}\n",
            "\\begin{longtable}{p{0.35\\textwidth}p{0.65\\textwidth}}\n",
            "INSERT NAME & INSERT AFFILIATION\\\\\n",
            "\\end{longtable}\n",
            "\\section*{Program Committee}\n",
            "\\begin{longtable}{p{0.35\\textwidth}p{0.65\\textwidth}}\n";
        foreach ($this->committee() as $p) {
            $line = $p["firstName"] . " " . $p["lastName"] . " & " . self::amp($p["affiliation"]);
            if (($p["country"] ?? "") !== "") {
                $line .= ", " . $p["country"];
            }
            echo $line, "\\\\\n";
        }
        echo "\\end{longtable}\n\n",
            "\\section*{Additional Reviewers}\n",
            "\\begin{multicols}{2}\n",
            "\\noindent \n";
        $external = $this->external_reviewers();
        foreach ($external as $p) {
            if (($p["firstName"] ?? "") !== "" && ($p["lastName"] ?? "") !== "") {
                echo $p["firstName"], " ", $p["lastName"], "\\\\\n";
            }
        }
        echo "\\end{multicols}\n\n",
            "% alternate version with affiliations (not requested by Springer)\n",
            "% \\begin{longtable}{p{0.35\\textwidth}p{0.65\\textwidth}}\n";
        foreach ($external as $p) {
            echo "% ", $p["firstName"], " ", $p["lastName"], " & ", self::amp($p["affiliation"]), "\\\\\n";
        }
        echo " %\\end{longtable}\n",
            "\\tableofcontents\n\n\\mainmatter\n\n",
            "\\setcounter{page}{1}\n";
    }

    /** Print a table-of-contents section heading.
     * @param string $name */
    static function print_section($name) {
        echo "\\addtocontents{toc}{\\protect\\section*{", $name, "}}\n";
    }

    /** Print a table-of-contents entry for `$paper`, a paper from iacr.org
     * (with `title`, `authorlist` of `name`/`affiliation`, and `pages`),
     * and return its page count (0 if unknown).
     * @param array<string,mixed> $paper
     * @return int */
    static function print_paper($paper) {
        $names = $affs = [];
        foreach ($paper["authorlist"] ?? [] as $au) {
            $names[] = $au["name"] ?? "";
            $affs[] = $au["affiliation"] ?? "";
            echo "% author: ", $au["name"] ?? "", ", ", $au["affiliation"] ?? "", "\n";
        }
        $pages = (int) ($paper["pages"] ?? 0);
        if ($pages > 0) {
            echo "% This paper has {$pages} pages in the PDF\n";
        }
        echo "\\author{", self::and_list($names), "}\n",
            "% \\institute{", self::amp(self::and_list($affs)), "}\n",
            "\\title{", $paper["title"] ?? "", "}\n",
            "\\maketitle\n";
        return $pages;
    }

    /** Print entries for `$papers`, advancing the page counter after each.
     * `$pageno` is the current page number.
     * @param iterable<array<string,mixed>> $papers
     * @param int &$pageno */
    static function print_papers($papers, &$pageno) {
        foreach ($papers as $paper) {
            $pageno += self::print_paper($paper);
            echo "\\setcounter{page}{{$pageno}}\n";
        }
    }

    /** @param list<string> $l
     * @return string */
    static private function and_list($l) {
        if (count($l) <= 1) {
            return $l[0] ?? "";
        }
        return join(", ", array_slice($l, 0, -1)) . " and " . $l[count($l) - 1];
    }

    static function print_end() {
        echo "\\phantom{Author Index}\n",
            "\\addcontentsline{toc}{title}{\\protect\\textbf{Author Index}}\n",
            "\\clearpage\n",
            "\\end{document}";
    }
}

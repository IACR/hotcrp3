<?php
// pages/p_iacr_authoremails.php -- author emails as TSV (/iacr_author_emails)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/getSpreadsheet.php. Springer asks for this spreadsheet when
// preparing LNCS volumes (see /iacr_lncs).

class IACR_AuthorEmails_Page {
    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        Navigation::header("Content-Disposition: attachment; filename=\"author_emails.tsv\"");
        Navigation::header("Content-Type: application/vnd.ms-excel");
        echo self::tsv($user->conf);
    }

    /** Escape tabs and newlines so `$s` fits in one TSV cell.
     * @param ?string $s
     * @return string */
    static function cell($s) {
        return preg_replace(['/\t/', '/\r?\n/'], ['\\t', '\\n'], $s ?? "");
    }

    /** Return one line per author account of each accepted paper.
     * @return string */
    static function tsv(Conf $conf) {
        $out = "paperId\tEmail\tGiven name\tFamily name\tTitle\r\n";
        $result = $conf->qe("select Paper.paperId, email, firstName, lastName, title
            from Paper
            join PaperConflict on (PaperConflict.paperId=Paper.paperId)
            join ContactInfo on (ContactInfo.contactId=PaperConflict.contactId)
            where conflictType>=? and timeWithdrawn=0 and outcome>0
            order by Paper.paperId, PaperConflict.contactId", CONFLICT_AUTHOR);
        while (($row = $result->fetch_row())) {
            $out .= join("\t", array_map("IACR_AuthorEmails_Page::cell", $row)) . "\r\n";
        }
        Dbl::free($result);
        return $out;
    }
}

<?php
// t_iacr_options.php -- HotCRP tests for IACR's fork: IACR submission fields
// that chairs may not delete. Not part of upstream HotCRP.

class IACR_Options_Tester {
    /** @var Conf
     * @readonly */
    public $conf;
    /** @var Contact
     * @readonly */
    public $u_chair;

    function __construct(Conf $conf) {
        $this->conf = $conf;
        $this->u_chair = $conf->checked_user_by_email("chair@_.com");
    }

    function test_iacr_fields_cannot_be_deleted() {
        // install IACR fields as the instance-creation script would
        $this->conf->save_refresh_setting("options", 1, json_encode_db([
            ["id" => 1, "name" => "Calories", "type" => "numeric", "order" => 7, "display" => "rest"],
            ["id" => PaperOption::IACR_COPYRIGHT_ID, "name" => "IACR Copyright Agreement", "type" => "text", "order" => 8],
            ["id" => PaperOption::IACR_FORMER_DOI_ID, "name" => "Former DOI", "type" => "text", "order" => 9],
            ["id" => 3999, "name" => "Ordinary field", "type" => "text", "order" => 10]
        ]));
        $this->conf->invalidate_caches("options");

        // the settings form locks down IACR fields
        $sv = new SettingValues($this->u_chair);
        ob_start();
        $sv->print("options");
        $html = ob_get_clean();
        foreach ([PaperOption::IACR_COPYRIGHT_ID, PaperOption::IACR_FORMER_DOI_ID] as $id) {
            xassert_eqq(preg_match('/<div id="sf\/(\d+)\/edit" class="[^"]*\boption-' . $id . '\b[^"]*\biacroption\b/', $html, $m), 1);
            $ctr = $m[1];
            $p0 = strpos($html, "<div id=\"sf/{$ctr}/edit\"");
            $p1 = strpos($html, "<div id=\"sf/" . ($ctr + 1) . "/", $p0) ?: strlen($html);
            $edit = substr($html, $p0, $p1 - $p0);
            xassert_str_contains($edit, "IACR field");
            xassert_str_contains($edit, "This IACR field cannot be deleted.");
            xassert(!str_contains($edit, "js-settings-sf-move delete"));
        }
        xassert_eqq(preg_match('/<div id="sf\/\d+\/edit" class="[^"]*\boption-3999\b[^"]*\biacroption\b/', $html), 0);

        foreach ([PaperOption::IACR_COPYRIGHT_ID, PaperOption::IACR_FORMER_DOI_ID] as $id) {
            xassert(PaperOption::is_iacr_undeletable_id($id));
            $sv = SettingValues::make_request($this->u_chair, [
                "has_sf" => 1,
                "sf/1/id" => (string) $id,
                "sf/1/delete" => "1"
            ]);
            xassert(!$sv->execute());
            xassert_str_contains($sv->full_feedback_text(), "This IACR field cannot be deleted");
            xassert(!!$this->conf->option_by_id($id));
        }

        // ordinary fields can still be deleted
        xassert(!PaperOption::is_iacr_undeletable_id(3999));
        $sv = SettingValues::make_request($this->u_chair, [
            "has_sf" => 1,
            "sf/1/id" => "3999",
            "sf/1/delete" => "1"
        ]);
        xassert($sv->execute(), $sv->full_feedback_text());
        xassert_eqq($this->conf->option_by_id(3999), null);
        xassert(!!$this->conf->option_by_id(PaperOption::IACR_FORMER_DOI_ID));

        TestRunner::reset_options();
    }

    /** @param ?string $exists_if */
    private function install_former_doi_fields($exists_if) {
        $doi = ["id" => PaperOption::IACR_FORMER_DOI_ID, "name" => "Former DOI", "type" => "text", "order" => 9];
        if ($exists_if !== null) {
            $doi["exists_if"] = $exists_if;
        }
        $this->conf->save_refresh_setting("options", 1, json_encode_db([
            ["id" => 1, "name" => "Calories", "type" => "numeric", "order" => 7, "display" => "rest"],
            ["id" => PaperOption::IACR_PUBTYPE_ID, "name" => "Publication type", "type" => "dropdown", "order" => 8,
             "values" => ["Research", "SoK", "Corrigendum", "Addendum", "Preface"]],
            $doi
        ]));
        $this->conf->invalidate_caches("options");
    }

    /** @param string $pubtype
     * @param ?string $doi
     * @return PaperStatus */
    private function save_former_doi($pubtype, $doi) {
        $popt = $this->conf->checked_option_by_id(PaperOption::IACR_PUBTYPE_ID);
        $dopt = $this->conf->checked_option_by_id(PaperOption::IACR_FORMER_DOI_ID);
        $ps = new PaperStatus($this->conf->root_user());
        $ps->save_paper_json((object) [
            "id" => 1, "status" => "submitted",
            $popt->json_key() => $pubtype, $dopt->json_key() => $doi
        ]);
        return $ps;
    }

    function test_former_doi_validation() {
        $this->install_former_doi_fields(null);
        $dopt = $this->conf->checked_option_by_id(PaperOption::IACR_FORMER_DOI_ID);
        xassert($dopt instanceof IACRFormerDOI_PaperOption);

        // other publication types need no DOI
        $ps = $this->save_former_doi("Research", null);
        xassert(!$ps->has_problem(), $ps->full_feedback_text());
        xassert_eqq($this->conf->checked_paper_by_id(1)->option(PaperOption::IACR_PUBTYPE_ID)->value, 1);

        // Corrigendum and Addendum require a DOI; paper 1 is submitted, so
        // an incomplete save is refused
        $ps = $this->save_former_doi("Corrigendum", null);
        xassert($ps->has_error());
        xassert_str_contains($ps->full_feedback_text(), "Enter the DOI of the original paper");
        xassert_eqq($this->conf->checked_paper_by_id(1)->option(PaperOption::IACR_PUBTYPE_ID)->value, 1);

        // the DOI must start with "10."
        $ps = $this->save_former_doi("Addendum", "doi:10.1007/abc");
        xassert($ps->has_error());
        xassert_str_contains($ps->full_feedback_text(), "DOI must start with ‘10.’");
        xassert_eqq($this->conf->checked_paper_by_id(1)->option(PaperOption::IACR_PUBTYPE_ID)->value, 1);

        // a good DOI saves, with surrounding whitespace removed
        $ps = $this->save_former_doi("Corrigendum", "  10.1007/abc ");
        xassert(!$ps->has_problem(), $ps->full_feedback_text());
        $p1 = $this->conf->checked_paper_by_id(1);
        xassert_eqq($p1->option(PaperOption::IACR_PUBTYPE_ID)->value, 3);
        xassert_eqq($p1->option(PaperOption::IACR_FORMER_DOI_ID)->data(), "10.1007/abc");
        xassert($p1->timeSubmitted > 0);

        // other publication types warn about a DOI, but save
        $ps = $this->save_former_doi("Research", "10.1007/abc");
        xassert(!$ps->has_error(), $ps->full_feedback_text());
        xassert_str_contains($ps->full_feedback_text(), "only used for a Corrigendum or Addendum");
        xassert_eqq($this->conf->checked_paper_by_id(1)->option(PaperOption::IACR_PUBTYPE_ID)->value, 1);

        $this->save_former_doi("Research", null);
        TestRunner::reset_options();
    }

    function test_former_doi_condition() {
        // the condition recommended for IACR's setup script
        $cond = "opt3777:Corrigendum OR opt3777:Addendum";
        $this->install_former_doi_fields($cond);
        $dopt = $this->conf->checked_option_by_id(PaperOption::IACR_FORMER_DOI_ID);
        xassert_eqq($dopt->exists_condition(), $cond);

        $ps = $this->save_former_doi("Corrigendum", "10.1007/abc");
        xassert(!$ps->has_problem(), $ps->full_feedback_text());
        xassert($dopt->test_exists($this->conf->checked_paper_by_id(1)));
        $ps = $this->save_former_doi("Addendum", "");
        xassert_str_contains($ps->full_feedback_text(), "Enter the DOI of the original paper");

        // the form can show and hide the field as the type changes
        $sj = $dopt->exists_script_expression($this->conf->checked_paper_by_id(1));
        xassert(is_array($sj) || is_object($sj));

        // hidden field: no requirement, and no warning about the old value
        $ps = $this->save_former_doi("Research", null);
        xassert(!$ps->has_problem(), $ps->full_feedback_text());
        $p1 = $this->conf->checked_paper_by_id(1);
        xassert(!$dopt->test_exists($p1));
        xassert_eqq($p1->option(PaperOption::IACR_FORMER_DOI_ID)->data(), "10.1007/abc");

        $this->save_former_doi("Research", "");
        TestRunner::reset_options();
    }
}

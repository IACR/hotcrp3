<?php
// listactions/la_getauthors.php -- HotCRP helper classes for list actions
// Copyright (c) 2006-2026 Eddie Kohler; see LICENSE.

class GetAuthors_ListAction extends ListAction {
    function allow(Contact $user, Qrequest $qreq) {
        return $user->can_view_some_authors();
    }
    function run(Contact $user, Qrequest $qreq, SearchSelection $ssel) {
        $conf = $user->conf;
        $prows = $ssel->paper_set($user, ["allConflictType" => 1]);
        $prows->apply_filter(function ($prow) use ($user) {
            return $user->allow_view_authors($prow);
        });
        foreach ($prows as $prow) {
            foreach ($prow->author_list() as $au) {
                if ($au->email) {
                    $conf->prefetch_user_by_email($au->email);
                    $conf->prefetch_cdb_user_by_email($au->email);
                }
            }
        }
        $texts = [];
        $has_iscontact = $has_country = $has_orcid = false;
        foreach ($prows as $prow) {
            $admin = $user->allow_admin($prow);
            $aucid = [];
            foreach ($prow->author_list() as $au) {
                $line = [
                    "paper" => $prow->paperId,
                    "title" => $prow->title,
                    "given_name" => $au->firstName,
                    "family_name" => $au->lastName,
                    "email" => $au->email,
                    "affiliation" => $au->affiliation
                ];
                $lemail = strtolower($au->email);
                // IACR: the iD entered on the paper wins over the account’s
                if ($au->has_orcid()) {
                    $line["orcid"] = $au->orcid;
                    $line["orcid_confirmed"] = $au->orcid_status($conf) === Author::ORCID_CONFIRMED ? "yes" : "no";
                    $has_orcid = true;
                }
                if ($au->email !== ""
                    && ($u = $conf->user_by_email($au->email))) {
                    if (!isset($line["orcid"])) {
                        $line["orcid"] = $u->decorated_orcid();
                        if ($line["orcid"] !== "") {
                            $line["orcid_confirmed"] = $u->confirmed_orcid() !== "" ? "yes" : "no";
                        }
                    }
                    $line["country"] = $u->country_code();
                    $has_orcid = $has_orcid || $line["orcid"] !== "";
                    $has_country = $has_country || $line["country"] !== "";
                    if ($admin) {
                        $line["iscontact"] = "yes";
                        $has_iscontact = true;
                    }
                    $aucid[$u->contactId] = true;
                }
                $texts[] = $line;
            }
            if ($admin) {
                foreach ($prow->contact_list() as $u) {
                    if (isset($aucid[$u->contactId])) {
                        continue;
                    }
                    $texts[] = $line = [
                        "paper" => $prow->paperId,
                        "title" => $prow->title,
                        "given_name" => $u->firstName,
                        "family_name" => $u->lastName,
                        "email" => $u->email,
                        "affiliation" => $u->affiliation,
                        "orcid" => $u->decorated_orcid(),
                        "country" => $u->country_code(),
                        "iscontact" => "nonauthor"
                    ];
                    if ($line["orcid"] !== "") {
                        $texts[count($texts) - 1]["orcid_confirmed"] = $u->confirmed_orcid() !== "" ? "yes" : "no";
                    }
                    $has_orcid = $has_orcid || $line["orcid"] !== "";
                    $has_country = $has_country || $line["country"] !== "";
                    $has_iscontact = true;
                }
            }
        }
        $header = ["paper", "title", "given_name", "family_name", "email", "affiliation"];
        if ($has_orcid) {
            $header[] = "orcid";
            $header[] = "orcid_confirmed";
        }
        if ($has_country) {
            $header[] = "country";
        }
        if ($has_iscontact) {
            $header[] = "iscontact";
        }
        return $user->conf->make_csvg("authors")->select($header)->append($texts);
    }
}

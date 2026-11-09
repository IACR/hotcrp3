<?php
// t_iacr_userstatus.php -- HotCRP tests for IACR's fork: account changes
// that IACR code hooks into. Not part of upstream HotCRP.

class IACR_UserStatus_Tester {
    /** @var Conf
     * @readonly */
    public $conf;

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    // IACR's AICheck webhook: the `user_roles_changed` hook runs whenever an
    // account's roles change, from the profile path and the user-list actions
    function test_user_roles_changed_hook() {
        $email = "aicheck-pc@_.com";
        $this->conf->qe("delete from ContactInfo where email=?", $email);
        $this->conf->invalidate_caches("users", "pc");
        $old_hooks = $this->conf->opt("hooks");
        $calls = [];
        $this->conf->set_opt("hooks", ["user_roles_changed" => [
            function (Contact $u, $old_roles, $new_roles, $actor, $created) use (&$calls) {
                $calls[] = [$u->email, $old_roles, $new_roles, $created];
            }
        ]]);

        // no hook unless AICheck is configured
        $us = new UserStatus($this->conf->root_user());
        $acct = $us->save_user((object) ["email" => $email, "roles" => ["pc"]]);
        xassert(!!$acct, $us->full_feedback_text());
        xassert_eqq($calls, []);
        $this->conf->qe("delete from ContactInfo where email=?", $email);
        $this->conf->invalidate_caches("users", "pc");

        $this->conf->set_opt("aicheckSecret", "x");
        $us = new UserStatus($this->conf->root_user());
        $acct = $us->save_user((object) ["email" => $email, "roles" => ["pc"]]);
        xassert(!!$acct, $us->full_feedback_text());
        xassert_eqq($calls, [[$email, 0, Contact::ROLE_PC, true]]);

        // other changes do not call it
        $calls = [];
        $acct = (new UserStatus($this->conf->root_user()))->save_user((object) ["email" => $email, "affiliation" => "IACR"]);
        xassert_eqq($calls, []);

        $acct = (new UserStatus($this->conf->root_user()))->save_user((object) ["email" => $email, "roles" => ["chair"]]);
        xassert_eqq($calls, [[$email, Contact::ROLE_PC, Contact::ROLE_PC | Contact::ROLE_CHAIR, false]]);

        // the user list's PC actions
        $calls = [];
        $ua = new UserActions($this->conf->root_user());
        $ua->remove_pc([$acct->contactId]);
        xassert_eqq($calls, [[$email, Contact::ROLE_PC | Contact::ROLE_CHAIR, 0, false]]);
        $calls = [];
        $ua->add_pc([$acct->contactId]);
        xassert_eqq($calls, [[$email, 0, Contact::ROLE_PC, false]]);

        $this->conf->set_opt("aicheckSecret", null);
        $this->conf->set_opt("hooks", $old_hooks);
        $this->conf->qe("delete from ContactInfo where email=?", $email);
        $this->conf->invalidate_caches("users", "pc");
    }
}

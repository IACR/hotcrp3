<?php
// src/help/h_iacr.php -- HotCRP help functions

class IACR_HelpTopic {
    /** @var Contact */
    private $user;
    
    function __construct(HelpRenderer $hth) {
      $this->user = $hth->user;
    }

    static function print(HelpRenderer $hth) {
      echo $hth->subhead("Available only to program chairs");
      if ($hth->user->privChair) {
        echo "<p><a href=\"", htmlspecialchars($hth->conf->hoturl("iacr_home")), "\">Located here</a></p>";
      }
    }
}

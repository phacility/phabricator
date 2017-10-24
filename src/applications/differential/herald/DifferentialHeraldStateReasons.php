<?php

final class DifferentialHeraldStateReasons
  extends HeraldStateReasons {

  const REASON_DRAFT = 'differential.draft';
  const REASON_UNCHANGED = 'differential.unchanged';

  public function explainReason($reason) {
    $reasons = array(
      self::REASON_DRAFT => pht(
        'This revision is still an unsubmitted draft, so mail will not '.
        'be sent yet.'),
      self::REASON_UNCHANGED => pht(
        'The update which triggered Herald did not update the diff for '.
        'this revision, so builds will not run.'),
    );

    return idx($reasons, $reason);
  }

}

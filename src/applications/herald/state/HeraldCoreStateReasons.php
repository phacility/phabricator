<?php

final class HeraldCoreStateReasons
  extends HeraldStateReasons {

  const REASON_SILENT = 'core.silent';

  public function explainReason($reason) {
    $reasons = array(
      self::REASON_SILENT => pht(
        'This change applied silently, so mail and other notifications '.
        'will not be sent.'),
    );

    return idx($reasons, $reason);
  }

}

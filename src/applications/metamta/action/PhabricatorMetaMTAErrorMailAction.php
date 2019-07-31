<?php

final class PhabricatorMetaMTAErrorMailAction extends PhabricatorSystemAction {

  const TYPECONST = 'email.error';

  public function getScoreThreshold() {
    return 6 / phutil_units('1 hour in seconds');
  }

}

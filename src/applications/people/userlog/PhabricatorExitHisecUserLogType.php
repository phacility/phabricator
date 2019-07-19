<?php

final class PhabricatorExitHisecUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'hisec-exit';

  public function getLogTypeName() {
    return pht('Hisec: Exit');
  }

}

<?php

final class PhabricatorEnterHisecUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'hisec-enter';

  public function getLogTypeName() {
    return pht('Hisec: Enter');
  }

}

<?php

final class PhabricatorFailHisecUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'hisec-fail';

  public function getLogTypeName() {
    return pht('Hisec: Failed Attempt');
  }

}

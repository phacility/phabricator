<?php

final class PhabricatorRemoveMultifactorUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'multi-remove';

  public function getLogTypeName() {
    return pht('Multi-Factor: Remove Factor');
  }

}

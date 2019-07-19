<?php

final class PhabricatorAddMultifactorUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'multi-add';

  public function getLogTypeName() {
    return pht('Multi-Factor: Add Factor');
  }

}

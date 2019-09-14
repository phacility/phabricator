<?php

final class PhabricatorVerifyEmailUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'email-verify';

  public function getLogTypeName() {
    return pht('Email: Verify Address');
  }

}

<?php

final class PhabricatorEmailLoginUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'email-login';

  public function getLogTypeName() {
    return pht('Email: Recovery Link');
  }

}

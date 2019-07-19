<?php

final class PhabricatorAddEmailUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'email-add';

  public function getLogTypeName() {
    return pht('Email: Add Address');
  }

}

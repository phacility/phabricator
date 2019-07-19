<?php

final class PhabricatorRemoveEmailUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'email-remove';

  public function getLogTypeName() {
    return pht('Email: Remove Address');
  }

}

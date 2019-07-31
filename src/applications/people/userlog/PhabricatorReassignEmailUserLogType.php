<?php

final class PhabricatorReassignEmailUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'email-reassign';

  public function getLogTypeName() {
    return pht('Email: Reassign');
  }

}

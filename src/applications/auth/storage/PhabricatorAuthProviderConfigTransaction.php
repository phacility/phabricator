<?php

final class PhabricatorAuthProviderConfigTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_ENABLE         = 'config:enable';
  const TYPE_REGISTRATION   = 'config:registration';
  const TYPE_LINK           = 'config:link';
  const TYPE_UNLINK         = 'config:unlink';
  const TYPE_PROPERTY       = 'config:property';

  public function getApplicationName() {
    return 'auth';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_AUTH;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getApplicationObjectTypeName() {
    return pht('authentication provider');
  }

}


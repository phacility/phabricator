<?php

final class PhabricatorAuthProviderConfigTransaction
  extends PhabricatorApplicationTransaction {

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


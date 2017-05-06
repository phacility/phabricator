<?php

final class PassphraseCredentialTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'passphrase';
  }

  public function getApplicationTransactionType() {
    return PassphraseCredentialPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getBaseTransactionClass() {
    return 'PassphraseCredentialTransactionType';
  }

}

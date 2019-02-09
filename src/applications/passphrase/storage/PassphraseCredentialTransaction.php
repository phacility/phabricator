<?php

final class PassphraseCredentialTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'passphrase';
  }

  public function getApplicationTransactionType() {
    return PassphraseCredentialPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PassphraseCredentialTransactionType';
  }

}

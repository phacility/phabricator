<?php

final class PassphraseCredentialTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PassphraseCredentialTransaction();
  }

}

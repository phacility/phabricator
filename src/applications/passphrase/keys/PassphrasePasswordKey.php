<?php

final class PassphrasePasswordKey extends PassphraseAbstractKey {

  public static function loadFromPHID($phid, PhabricatorUser $viewer) {
    $key = new PassphraseSSHKey();
    return $key->loadAndValidateFromPHID(
      $phid,
      $viewer,
      PassphraseCredentialTypePassword::PROVIDES_TYPE);
  }

  public function getPasswordEnvelope() {
    return $this->requireCredential()->getSecret();
  }

}

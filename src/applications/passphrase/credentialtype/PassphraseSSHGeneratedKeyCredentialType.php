<?php

final class PassphraseSSHGeneratedKeyCredentialType
  extends PassphraseSSHPrivateKeyCredentialType {

  const CREDENTIAL_TYPE = 'ssh-generated-key';

  public function getCredentialType() {
    return self::CREDENTIAL_TYPE;
  }

  public function getCredentialTypeName() {
    return pht('SSH Private Key (Generated)');
  }

  public function getCredentialTypeDescription() {
    return pht('Generate an SSH keypair.');
  }

  public function getSecretLabel() {
    return pht('Generated Key');
  }

  public function didInitializeNewCredential(
    PhabricatorUser $actor,
    PassphraseCredential $credential) {

    $pair = PhabricatorSSHKeyGenerator::generateKeypair();
    list($public_key, $private_key) = $pair;

    $credential->attachSecret(new PhutilOpaqueEnvelope($private_key));

    return $credential;
  }

}

<?php

final class PassphraseSSHPrivateKeyTextCredentialType
  extends PassphraseSSHPrivateKeyCredentialType {

  const CREDENTIAL_TYPE = 'ssh-key-text';

  public function getCredentialType() {
    return self::CREDENTIAL_TYPE;
  }

  public function getCredentialTypeName() {
    return pht('SSH Private Key');
  }

  public function getCredentialTypeDescription() {
    return pht('Store the plaintext of an SSH private key.');
  }

  public function getSecretLabel() {
    return pht('Private Key');
  }

  public function shouldShowPasswordField() {
    return true;
  }

  public function getPasswordLabel() {
    return pht('Password for Key');
  }

  public function requiresPassword(PhutilOpaqueEnvelope $secret) {
    // According to the internet, this is the canonical test for an SSH private
    // key with a password.
    return preg_match('/ENCRYPTED/', $secret->openEnvelope());
  }

  public function decryptSecret(
    PhutilOpaqueEnvelope $secret,
    PhutilOpaqueEnvelope $password) {

    $tmp = new TempFile();
    Filesystem::writeFile($tmp, $secret->openEnvelope());

    if (!Filesystem::binaryExists('ssh-keygen')) {
      throw new Exception(
        pht(
          'Decrypting SSH keys requires the `%s` binary, but it '.
          'is not available in %s. Either make it available or strip the '.
          'password fromt his SSH key manually before uploading it.',
          'ssh-keygen',
          '$PATH'));
    }

    list($err, $stdout, $stderr) = exec_manual(
      'ssh-keygen -p -P %P -N %s -f %s',
      $password,
      '',
      (string)$tmp);

    if ($err) {
      return null;
    } else {
      return new PhutilOpaqueEnvelope(Filesystem::readFile($tmp));
    }
  }

}

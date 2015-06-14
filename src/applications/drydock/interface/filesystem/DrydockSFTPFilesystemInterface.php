<?php

final class DrydockSFTPFilesystemInterface extends DrydockFilesystemInterface {

  private $passphraseSSHKey;

  private function openCredentialsIfNotOpen() {
    if ($this->passphraseSSHKey !== null) {
      return;
    }

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->getConfig('credential')))
      ->needSecrets(true)
      ->executeOne();

    if ($credential->getProvidesType() !==
      PassphraseSSHPrivateKeyCredentialType::PROVIDES_TYPE) {
      throw new Exception(pht('Only private key credentials are supported.'));
    }

    $this->passphraseSSHKey = PassphraseSSHKey::loadFromPHID(
      $credential->getPHID(),
      PhabricatorUser::getOmnipotentUser());
  }

  private function getExecFuture($path) {
    $this->openCredentialsIfNotOpen();

    return new ExecFuture(
      'sftp -o "StrictHostKeyChecking no" -P %s -i %P %P@%s',
      $this->getConfig('port'),
      $this->passphraseSSHKey->getKeyfileEnvelope(),
      $this->passphraseSSHKey->getUsernameEnvelope(),
      $this->getConfig('host'));
  }

  public function readFile($path) {
    $target = new TempFile();
    $future = $this->getExecFuture($path);
    $future->write(csprintf('get %s %s', $path, $target));
    $future->resolvex();
    return Filesystem::readFile($target);
  }

  public function saveFile($path, $name) {
    $data = $this->readFile($path);
    $file = PhabricatorFile::newFromFileData(
      $data,
      array('name' => $name));
    $file->setName($name);
    $file->save();
    return $file;
  }

  public function writeFile($path, $data) {
    $source = new TempFile();
    Filesystem::writeFile($source, $data);
    $future = $this->getExecFuture($path);
    $future->write(csprintf('put %s %s', $source, $path));
    $future->resolvex();
  }

}

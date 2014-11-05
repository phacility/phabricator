<?php

final class AlmanacManagementRegisterWorkflow
  extends AlmanacManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('register')
      ->setSynopsis(pht('Register this host for authorized Conduit access.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    if (Filesystem::pathExists(AlmanacConduitUtil::getHostPrivateKeyPath())) {
      throw new Exception(
        'This host already has a private key for Conduit access.');
    }

    $pair = PhabricatorSSHKeyGenerator::generateKeypair();
    list($public_key, $private_key) = $pair;

    $host = id(new AlmanacDevice())
      ->setName(php_uname('n'))
      ->save();

    id(new AlmanacProperty())
      ->setObjectPHID($host->getPHID())
      ->setName('conduitPublicOpenSSHKey')
      ->setValue($public_key)
      ->save();

    id(new AlmanacProperty())
      ->setObjectPHID($host->getPHID())
      ->setName('conduitPublicOpenSSLKey')
      ->setValue($this->convertToOpenSSLPublicKey($public_key))
      ->save();

    Filesystem::writeFile(
      AlmanacConduitUtil::getHostPrivateKeyPath(),
      $private_key);

    Filesystem::writeFile(
      AlmanacConduitUtil::getHostIDPath(),
      $host->getID());

    $console->writeOut("Registered as device %d.\n", $host->getID());
  }

  private function convertToOpenSSLPublicKey($openssh_public_key) {
    $ssh_public_key_file = new TempFile();
    Filesystem::writeFile($ssh_public_key_file, $openssh_public_key);

    list($public_key, $stderr) = id(new ExecFuture(
      'ssh-keygen -e -f %s -m pkcs8',
      $ssh_public_key_file))->resolvex();

    unset($ssh_public_key_file);

    return $public_key;
  }

}

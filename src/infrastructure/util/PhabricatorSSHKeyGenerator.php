<?php

final class PhabricatorSSHKeyGenerator extends Phobject {

  public static function assertCanGenerateKeypair() {
    $binary = 'ssh-keygen';
    if (!Filesystem::resolveBinary($binary)) {
      throw new Exception(
        pht(
          'Can not generate keys: unable to find "%s" in PATH!',
          $binary));
    }
  }

  public static function generateKeypair() {
    self::assertCanGenerateKeypair();

    $tempfile = new TempFile();
    $keyfile = dirname($tempfile).DIRECTORY_SEPARATOR.'keytext';

    execx(
      'ssh-keygen -t rsa -N %s -f %s',
      '',
      $keyfile);

    $public_key = Filesystem::readFile($keyfile.'.pub');
    $private_key = Filesystem::readFile($keyfile);

    return array($public_key, $private_key);
  }

}

<?php

final class AlmanacManagementRegisterWorkflow
  extends AlmanacManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('register')
      ->setSynopsis(pht('Register this host as an Almanac device.'))
      ->setArguments(
        array(
          array(
            'name' => 'device',
            'param' => 'name',
            'help' => pht('Almanac device name to register.'),
          ),
          array(
            'name' => 'private-key',
            'param' => 'key',
            'help' => pht('Path to a private key for the host.'),
          ),
          array(
            'name' => 'allow-key-reuse',
            'help' => pht(
              'Register even if another host is already registered with this '.
              'keypair. This is an advanced featuer which allows a pool of '.
              'devices to share credentials.'),
          ),
          array(
            'name' => 'identify-as',
            'param' => 'name',
            'help' => pht(
              'Specify an alternate host identity. This is an advanced '.
              'feature which allows a pool of devices to share credentials.'),
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Register this host even if keys already exist.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $device_name = $args->getArg('device');
    if (!strlen($device_name)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a device with --device.'));
    }

    $device = id(new AlmanacDeviceQuery())
      ->setViewer($this->getViewer())
      ->withNames(array($device_name))
      ->executeOne();
    if (!$device) {
      throw new PhutilArgumentUsageException(
        pht('No such device "%s" exists!', $device_name));
    }

    $private_key_path = $args->getArg('private-key');
    if (!strlen($private_key_path)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a private key with --private-key.'));
    }

    if (!Filesystem::pathExists($private_key_path)) {
      throw new PhutilArgumentUsageException(
        pht('Private key "%s" does not exist!', $private_key_path));
    }

    $raw_private_key = Filesystem::readFile($private_key_path);

    $phd_user = PhabricatorEnv::getEnvConfig('phd.user');
    if (!$phd_user) {
      throw new PhutilArgumentUsageException(
        pht(
          'Config option "phd.user" is not set. You must set this option '.
          'so the private key can be stored with the correct permissions.'));
    }

    $tmp = new TempFile();
    list($err) = exec_manual('chown %s %s', $phd_user, $tmp);
    if ($err) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to change ownership of a file to daemon user "%s". Run '.
          'this command as %s or root.',
          $phd_user,
          $phd_user));
    }

    $stored_public_path = AlmanacKeys::getKeyPath('device.pub');
    $stored_private_path = AlmanacKeys::getKeyPath('device.key');
    $stored_device_path = AlmanacKeys::getKeyPath('device.id');

    if (!$args->getArg('force')) {
      if (Filesystem::pathExists($stored_public_path)) {
        throw new PhutilArgumentUsageException(
          pht(
            'This host already has a registered public key ("%s"). '.
            'Remove this key before registering the host, or use '.
            '--force to overwrite it.',
            Filesystem::readablePath($stored_public_path)));
      }

      if (Filesystem::pathExists($stored_private_path)) {
        throw new PhutilArgumentUsageException(
          pht(
            'This host already has a registered private key ("%s"). '.
            'Remove this key before registering the host, or use '.
            '--force to overwrite it.',
            Filesystem::readablePath($stored_private_path)));
      }
    }

    // NOTE: We're writing the private key here so we can change permissions
    // on it without causing weird side effects to the file specified with
    // the `--private-key` flag. The file needs to have restrictive permissions
    // before `ssh-keygen` will willingly operate on it.
    $tmp_private = new TempFile();
    Filesystem::changePermissions($tmp_private, 0600);
    execx('chown %s %s', $phd_user, $tmp_private);
    Filesystem::writeFile($tmp_private, $raw_private_key);

    list($raw_public_key) = execx('ssh-keygen -y -f %s', $tmp_private);

    $key_object = PhabricatorAuthSSHPublicKey::newFromRawKey($raw_public_key);

    $public_key = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($this->getViewer())
      ->withKeys(array($key_object))
      ->executeOne();

    if ($public_key) {
      if ($public_key->getObjectPHID() !== $device->getPHID()) {
        throw new PhutilArgumentUsageException(
          pht(
            'The public key corresponding to the given private key is '.
            'already associated with an object other than the specified '.
            'device. You can not use a single private key to identify '.
            'multiple devices or users.'));
      } else if (!$public_key->getIsTrusted()) {
        throw new PhutilArgumentUsageException(
          pht(
            'The public key corresponding to the given private key is '.
            'already associated with the device, but is not trusted. '.
            'Registering this key would trust the other entities which '.
            'hold it. Use a unique key, or explicitly enable trust for the '.
            'current key.'));
      } else if (!$args->getArg('allow-key-reuse')) {
        throw new PhutilArgumentUsageException(
          pht(
            'The public key corresponding to the given private key is '.
            'already associated with the device. If you do not want to '.
            'use a unique key, use --allow-key-reuse to permit '.
            'reassociation.'));
      }
    } else {
      $public_key = id(new PhabricatorAuthSSHKey())
        ->setObjectPHID($device->getPHID())
        ->attachObject($device)
        ->setName($device->getSSHKeyDefaultName())
        ->setKeyType($key_object->getType())
        ->setKeyBody($key_object->getBody())
        ->setKeyComment(pht('Registered'))
        ->setIsTrusted(1);
    }


    $console->writeOut(
      "%s\n",
      pht('Installing public key...'));

    $tmp_public = new TempFile();
    Filesystem::changePermissions($tmp_public, 0600);
    execx('chown %s %s', $phd_user, $tmp_public);
    Filesystem::writeFile($tmp_public, $raw_public_key);
    execx('mv -f %s %s', $tmp_public, $stored_public_path);

    $console->writeOut(
      "%s\n",
      pht('Installing private key...'));
    execx('mv -f %s %s', $tmp_private, $stored_private_path);

    $raw_device = $device_name;
    $identify_as = $args->getArg('identify-as');
    if (strlen($identify_as)) {
      $raw_device = $identify_as;
    }

    $console->writeOut(
      "%s\n",
      pht('Installing device %s...', $raw_device));

    // The permissions on this file are more open because the webserver also
    // needs to read it.
    $tmp_device = new TempFile();
    Filesystem::changePermissions($tmp_device, 0644);
    execx('chown %s %s', $phd_user, $tmp_device);
    Filesystem::writeFile($tmp_device, $raw_device);
    execx('mv -f %s %s', $tmp_device, $stored_device_path);

    if (!$public_key->getID()) {
      $console->writeOut(
        "%s\n",
        pht('Registering device key...'));
      $public_key->save();
    }

    $console->writeOut(
      "**<bg:green> %s </bg>** %s\n",
      pht('HOST REGISTERED'),
      pht(
        'This host has been registered as "%s" and a trusted keypair '.
        'has been installed.',
        $raw_device));
  }

}

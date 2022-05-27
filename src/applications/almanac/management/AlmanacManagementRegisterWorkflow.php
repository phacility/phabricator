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
            'name' => 'identify-as',
            'param' => 'name',
            'help' => pht(
              'Specify an alternate host identity. This is an advanced '.
              'feature which allows a pool of devices to share credentials.'),
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Register this host even if keys already exist on disk.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $device_name = $args->getArg('device');
    if (!strlen($device_name)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a device with --device.'));
    }

    $device = id(new AlmanacDeviceQuery())
      ->setViewer($viewer)
      ->withNames(array($device_name))
      ->executeOne();
    if (!$device) {
      throw new PhutilArgumentUsageException(
        pht('No such device "%s" exists!', $device_name));
    }

    $identify_as = $args->getArg('identify-as');

    $raw_device = $device_name;
    if (strlen($identify_as)) {
      $raw_device = $identify_as;
    }

    $identity_device = id(new AlmanacDeviceQuery())
      ->setViewer($viewer)
      ->withNames(array($raw_device))
      ->executeOne();
    if (!$identity_device) {
      throw new PhutilArgumentUsageException(
        pht(
          'No such device "%s" exists!', $raw_device));
    }

    $private_key_path = $args->getArg('private-key');
    if (!strlen($private_key_path)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a private key with --private-key.'));
    }

    if (!Filesystem::pathExists($private_key_path)) {
      throw new PhutilArgumentUsageException(
        pht('No private key exists at path "%s"!', $private_key_path));
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
          'Unable to change ownership of an identity file to daemon user '.
          '"%s". Run this command as %s or root.',
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
      ->withIsActive(true)
      ->executeOne();

    if (!$public_key) {
      throw new PhutilArgumentUsageException(
        pht(
          'The public key corresponding to the given private key is unknown. '.
          'Associate the public key with an Almanac device in the web '.
          'interface before registering hosts with it.'));
    }

    if ($public_key->getObjectPHID() !== $device->getPHID()) {
      $public_phid = $public_key->getObjectPHID();
      $public_handles = $viewer->loadHandles(array($public_phid));
      $public_handle = $public_handles[$public_phid];

      throw new PhutilArgumentUsageException(
        pht(
          'The public key corresponding to the given private key is already '.
          'associated with an object ("%s") other than the specified '.
          'device ("%s"). You can not use a single private key to identify '.
          'multiple devices or users.',
          $public_handle->getFullName(),
          $device->getName()));
    }

    if (!$public_key->getIsTrusted()) {
      throw new PhutilArgumentUsageException(
        pht(
          'The public key corresponding to the given private key is '.
          'properly associated with the device, but is not yet trusted. '.
          'Trust this key before registering devices with it.'));
    }

    echo tsprintf(
      "%s\n",
      pht('Installing public key...'));

    $tmp_public = new TempFile();
    Filesystem::changePermissions($tmp_public, 0600);
    execx('chown %s %s', $phd_user, $tmp_public);
    Filesystem::writeFile($tmp_public, $raw_public_key);
    execx('mv -f %s %s', $tmp_public, $stored_public_path);

    echo tsprintf(
      "%s\n",
      pht('Installing private key...'));
    execx('mv -f %s %s', $tmp_private, $stored_private_path);

    echo tsprintf(
      "%s\n",
      pht('Installing device %s...', $raw_device));

    // The permissions on this file are more open because the webserver also
    // needs to read it.
    $tmp_device = new TempFile();
    Filesystem::changePermissions($tmp_device, 0644);
    execx('chown %s %s', $phd_user, $tmp_device);
    Filesystem::writeFile($tmp_device, $raw_device);
    execx('mv -f %s %s', $tmp_device, $stored_device_path);

    echo tsprintf(
      "**<bg:green> %s </bg>** %s\n",
      pht('HOST REGISTERED'),
      pht(
        'This host has been registered as "%s" and a trusted keypair '.
        'has been installed.',
        $raw_device));
  }

}

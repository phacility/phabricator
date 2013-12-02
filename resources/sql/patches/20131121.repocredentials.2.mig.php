<?php

$table = new PhabricatorRepository();
$conn_w = $table->establishConnection('w');
$viewer = PhabricatorUser::getOmnipotentUser();

$map = array();
foreach (new LiskMigrationIterator($table) as $repository) {
  $callsign = $repository->getCallsign();
  echo "Examining repository {$callsign}...\n";

  if ($repository->getCredentialPHID()) {
    echo "...already has a Credential.\n";
    continue;
  }

  $raw_uri = $repository->getRemoteURI();
  if (!$raw_uri) {
    echo "...no remote URI.\n";
    continue;
  }

  $uri = new PhutilURI($raw_uri);

  $proto = strtolower($uri->getProtocol());
  if ($proto == 'http' || $proto == 'https' || $proto == 'svn') {
    $username = $repository->getDetail('http-login');
    $secret = $repository->getDetail('http-pass');
    $type = PassphraseCredentialTypePassword::CREDENTIAL_TYPE;
  } else {
    $username = $repository->getDetail('ssh-login');
    if (!$username) {
      // If there's no explicit username, check for one in the URI. This is
      // possible with older repositories.
      $username = $uri->getUser();
      if (!$username) {
        // Also check for a Git/SCP-style URI.
        $git_uri = new PhutilGitURI($raw_uri);
        $username = $git_uri->getUser();
      }
    }
    $file = $repository->getDetail('ssh-keyfile');
    if ($file) {
      $secret = $file;
      $type = PassphraseCredentialTypeSSHPrivateKeyFile::CREDENTIAL_TYPE;
    } else {
      $secret = $repository->getDetail('ssh-key');
      $type = PassphraseCredentialTypeSSHPrivateKeyText::CREDENTIAL_TYPE;
    }
  }

  if (!$username || !$secret) {
    echo "...no credentials set.\n";
    continue;
  }

  $map[$type][$username][$secret][] = $repository;
  echo "...will migrate.\n";
}

$passphrase = new PassphraseSecret();
$passphrase->openTransaction();
$table->openTransaction();

foreach ($map as $credential_type => $credential_usernames) {
  $type = PassphraseCredentialType::getTypeByConstant($credential_type);
  foreach ($credential_usernames as $username => $credential_secrets) {
    foreach ($credential_secrets as $secret_plaintext => $repositories) {
      $callsigns = mpull($repositories, 'getCallsign');
      $name = pht(
        'Migrated Repository Credential (%s)',
        phutil_utf8_shorten(implode(', ', $callsigns), 128));

      echo "Creating: {$name}...\n";

      $secret = id(new PassphraseSecret())
        ->setSecretData($secret_plaintext)
        ->save();

      $secret_id = $secret->getID();

      $credential = PassphraseCredential::initializeNewCredential($viewer)
        ->setCredentialType($type->getCredentialType())
        ->setProvidesType($type->getProvidesType())
        ->setViewPolicy(PhabricatorPolicies::POLICY_ADMIN)
        ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN)
        ->setName($name)
        ->setUsername($username)
        ->setSecretID($secret_id)
        ->save();

      foreach ($repositories as $repository) {
        queryfx(
          $conn_w,
          'UPDATE %T SET credentialPHID = %s WHERE id = %d',
          $table->getTableName(),
          $credential->getPHID(),
          $repository->getID());

        $edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_USES_CREDENTIAL;

        id(new PhabricatorEdgeEditor())
          ->setActor($viewer)
          ->addEdge($repository->getPHID(), $edge_type, $credential->getPHID())
          ->save();
      }
    }
  }
}

$table->saveTransaction();
$passphrase->saveTransaction();

echo "Done.\n";

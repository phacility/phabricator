<?php

$table = new PhabricatorRepository();
$conn_w = $table->establishConnection('w');
$viewer = PhabricatorUser::getOmnipotentUser();

$map = array();
foreach (new LiskMigrationIterator($table) as $repository) {
  $callsign = $repository->getCallsign();
  echo pht('Examining repository %s...', $callsign)."\n";

  if ($repository->getCredentialPHID()) {
    echo pht('...already has a Credential.')."\n";
    continue;
  }

  $raw_uri = $repository->getRemoteURI();
  if (!$raw_uri) {
    echo pht('...no remote URI.')."\n";
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
    echo pht('...no credentials set.')."\n";
    continue;
  }

  $map[$type][$username][$secret][] = $repository;
  echo pht('...will migrate.')."\n";
}

$passphrase = new PassphraseSecret();
$passphrase->openTransaction();
$table->openTransaction();

foreach ($map as $credential_type => $credential_usernames) {
  $type = PassphraseCredentialType::getTypeByConstant($credential_type);
  foreach ($credential_usernames as $username => $credential_secrets) {
    foreach ($credential_secrets as $secret_plaintext => $repositories) {
      $callsigns = mpull($repositories, 'getCallsign');

      $signs = implode(', ', $callsigns);

      $name = pht(
        'Migrated Repository Credential (%s)',
        id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(128)
          ->truncateString($signs));

      echo pht('Creating: %s...', $name)."\n";

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
        ->setSecretID($secret_id);

      $credential->setPHID($credential->generatePHID());

      queryfx(
        $credential->establishConnection('w'),
        'INSERT INTO %T (name, credentialType, providesType, viewPolicy,
          editPolicy, description, username, secretID, isDestroyed,
          phid, dateCreated, dateModified)
          VALUES (%s, %s, %s, %s, %s, %s, %s, %d, %d, %s, %d, %d)',
        $credential->getTableName(),
        $credential->getName(),
        $credential->getCredentialType(),
        $credential->getProvidesType(),
        $credential->getViewPolicy(),
        $credential->getEditPolicy(),
        $credential->getDescription(),
        $credential->getUsername(),
        $credential->getSecretID(),
        $credential->getIsDestroyed(),
        $credential->getPHID(),
        time(),
        time());

      foreach ($repositories as $repository) {
        queryfx(
          $conn_w,
          'UPDATE %T SET credentialPHID = %s WHERE id = %d',
          $table->getTableName(),
          $credential->getPHID(),
          $repository->getID());

        $edge_type = PhabricatorObjectUsesCredentialsEdgeType::EDGECONST;

        id(new PhabricatorEdgeEditor())
          ->addEdge($repository->getPHID(), $edge_type, $credential->getPHID())
          ->save();
      }
    }
  }
}

$table->saveTransaction();
$passphrase->saveTransaction();

echo pht('Done.')."\n";

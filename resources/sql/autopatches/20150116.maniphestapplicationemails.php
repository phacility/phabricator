<?php

$key = 'metamta.maniphest.public-create-email';
echo "Migrating `$key` to new application email infrastructure...\n";
$value = PhabricatorEnv::getEnvConfigIfExists($key);
$maniphest = new PhabricatorManiphestApplication();

if ($value) {
  try {
    PhabricatorMetaMTAApplicationEmail::initializeNewAppEmail(
      PhabricatorUser::getOmnipotentUser())
      ->setAddress($value)
      ->setApplicationPHID($maniphest->getPHID())
      ->save();
  } catch (AphrontDuplicateKeyQueryException $ex) {
    // already migrated?
  }
}

echo "Done.\n";

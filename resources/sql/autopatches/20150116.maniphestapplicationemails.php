<?php

$key = 'metamta.maniphest.public-create-email';
echo pht("Migrating `%s` to new application email infrastructure...\n", $key);
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
    // Already migrated?
  }
}

echo pht('Done.')."\n";

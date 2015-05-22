<?php

$key_files = 'metamta.files.public-create-email';
$key_paste = 'metamta.paste.public-create-email';
echo pht(
  "Migrating `%s` and `%s` to new application email infrastructure...\n",
  $key_files,
  $key_paste);

$value_files = PhabricatorEnv::getEnvConfigIfExists($key_files);
$files_app = new PhabricatorFilesApplication();

if ($value_files) {
  try {
    PhabricatorMetaMTAApplicationEmail::initializeNewAppEmail(
      PhabricatorUser::getOmnipotentUser())
      ->setAddress($value_files)
      ->setApplicationPHID($files_app->getPHID())
      ->save();
  } catch (AphrontDuplicateKeyQueryException $ex) {
    // Already migrated?
  }
}

$value_paste = PhabricatorEnv::getEnvConfigIfExists($key_paste);
$paste_app = new PhabricatorPasteApplication();

if ($value_paste) {
  try {
    PhabricatorMetaMTAApplicationEmail::initializeNewAppEmail(
      PhabricatorUser::getOmnipotentUser())
      ->setAddress($value_paste)
      ->setApplicationPHID($paste_app->getPHID())
      ->save();
  } catch (AphrontDuplicateKeyQueryException $ex) {
    // Already migrated?
  }
}

echo pht('Done.')."\n";

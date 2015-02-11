<?php

$key_files = 'metamta.files.public-create-email';
$key_paste = 'metamta.paste.public-create-email';
echo "Migrating `$key_files` and `$key_paste` to new application email ".
  "infrastructure...\n";

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
    // already migrated?
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
    // already migrated?
  }
}

echo "Done.\n";

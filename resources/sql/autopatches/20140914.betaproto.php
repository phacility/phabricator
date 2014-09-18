<?php

$old_key = 'phabricator.show-beta-applications';
$new_key = 'phabricator.show-prototypes';

echo "Migrating '{$old_key}' to '{$new_key}'...\n";

if (PhabricatorEnv::getEnvConfig($new_key)) {
  echo "Skipping migration, new data is already set.\n";
  return;
}

$old = PhabricatorEnv::getEnvConfigIfExists($old_key);
if (!$old) {
  echo "Skipping migration, old data does not exist.\n";
  return;
}

PhabricatorConfigEntry::loadConfigEntry($new_key)
  ->setIsDeleted(0)
  ->setValue($old)
  ->save();

echo "Done.\n";

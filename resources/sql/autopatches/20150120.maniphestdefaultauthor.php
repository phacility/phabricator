<?php

$key = 'metamta.maniphest.default-public-author';
echo "Migrating `$key` to new application email infrastructure...\n";
$value = PhabricatorEnv::getEnvConfigIfExists($key);
$maniphest = new PhabricatorManiphestApplication();
$config_key =
  PhabricatorMetaMTAApplicationEmail::CONFIG_DEFAULT_AUTHOR;

if ($value) {
  $app_emails = id(new PhabricatorMetaMTAApplicationEmailQuery())
    ->setViewer(PhabricatorUser::getOmnipotentUser())
    ->withApplicationPHIDs(array($maniphest->getPHID()))
    ->execute();

  foreach ($app_emails as $app_email) {
    $app_email->setConfigValue($config_key, $value);
    $app_email->save();
  }
}

echo "Done.\n";

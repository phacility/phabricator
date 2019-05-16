<?php

$applications = array(
  'Audit',
  'Auth',
  'Calendar',
  'ChatLog',
  'Conduit',
  'Config',
  'Conpherence',
  'Countdown',
  'Daemons',
  'Dashboard',
  'Differential',
  'Diffusion',
  'Diviner',
  'Doorkeeper',
  'Drydock',
  'Fact',
  'Feed',
  'Files',
  'Flags',
  'Harbormaster',
  'Help',
  'Herald',
  'Home',
  'Legalpad',
  'Macro',
  'MailingLists',
  'Maniphest',
  'Applications',
  'MetaMTA',
  'Notifications',
  'Nuance',
  'OAuthServer',
  'Owners',
  'Passphrase',
  'Paste',
  'People',
  'Phame',
  'Phlux',
  'Pholio',
  'Phortune',
  'PHPAST',
  'Phragment',
  'Phrequent',
  'Phriction',
  'Policy',
  'Ponder',
  'Project',
  'Releeph',
  'Repositories',
  'Search',
  'Settings',
  'Slowvote',
  'Subscriptions',
  'Support',
  'System',
  'Test',
  'Tokens',
  'Transactions',
  'Typeahead',
  'UIExamples',
  'XHProf',
);
$map = array();

foreach ($applications as $application) {
  $old_name = 'PhabricatorApplication'.$application;
  $new_name = 'Phabricator'.$application.'Application';
  $map[$old_name] = $new_name;
}


/* -(  User preferences  )--------------------------------------------------- */


// This originally migrated pinned applications in user preferences, but was
// removed to simplify preference changes after about 22 months.


/* -(  Dashboard installs  )------------------------------------------------- */

// This originally migrated dashboard install locations, but was removed
// after about 5 years.

/* -(  Phabricator configuration  )------------------------------------------ */

$config_key = 'phabricator.uninstalled-applications';
echo pht('Migrating `%s` config...', $config_key)."\n";

$config = PhabricatorConfigEntry::loadConfigEntry($config_key);
$old_config = $config->getValue();
$new_config = array();

if ($old_config) {
  foreach ($old_config as $application => $uninstalled) {
    $new_config[idx($map, $application, $application)] = $uninstalled;
  }

  $config
    ->setIsDeleted(0)
    ->setValue($new_config)
    ->save();
}


/* -(  phabricator.application-settings  )----------------------------------- */

$config_key = 'phabricator.application-settings';
echo pht('Migrating `%s` config...', $config_key)."\n";

$config = PhabricatorConfigEntry::loadConfigEntry($config_key);
$old_config = $config->getValue();
$new_config = array();

if ($old_config) {
  foreach ($old_config as $application => $settings) {
    $application = preg_replace('/^PHID-APPS-/', '', $application);
    $new_config['PHID-APPS-'.idx($map, $application, $application)] = $settings;
  }

  $config
    ->setIsDeleted(0)
    ->setValue($new_config)
    ->save();
}

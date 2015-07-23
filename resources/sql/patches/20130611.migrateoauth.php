<?php

// NOTE: We aren't using PhabricatorUserOAuthInfo anywhere here because it is
// getting nuked in a future diff.

$table = new PhabricatorUser();
$table_name = 'user_oauthinfo';
$conn_w = $table->establishConnection('w');

$xaccount = new PhabricatorExternalAccount();

echo pht('Migrating OAuth to %s...', 'ExternalAccount')."\n";

$domain_map = array(
  'disqus'      => 'disqus.com',
  'facebook'    => 'facebook.com',
  'github'      => 'github.com',
  'google'      => 'google.com',
);

try {
  $phabricator_oauth_uri = new PhutilURI(
    PhabricatorEnv::getEnvConfig('phabricator.oauth-uri'));
  $domain_map['phabricator'] = $phabricator_oauth_uri->getDomain();
} catch (Exception $ex) {
  // Ignore; this likely indicates that we have removed `phabricator.oauth-uri`
  // in some future diff.
}

$rows = queryfx_all(
  $conn_w,
  'SELECT * FROM user_oauthinfo');
foreach ($rows as $row) {
  echo pht('Migrating row ID #%d.', $row['id'])."\n";
  $user = id(new PhabricatorUser())->loadOneWhere(
    'id = %d',
    $row['userID']);
  if (!$user) {
    echo pht('Bad user ID!')."\n";
    continue;
  }

  $domain = idx($domain_map, $row['oauthProvider']);
  if (empty($domain)) {
    echo pht('Unknown OAuth provider!')."\n";
    continue;
  }


  $xaccount = id(new PhabricatorExternalAccount())
    ->setUserPHID($user->getPHID())
    ->setAccountType($row['oauthProvider'])
    ->setAccountDomain($domain)
    ->setAccountID($row['oauthUID'])
    ->setAccountURI($row['accountURI'])
    ->setUsername($row['accountName'])
    ->setDateCreated($row['dateCreated']);

  try {
    $xaccount->save();
  } catch (Exception $ex) {
    phlog($ex);
  }
}

echo pht('Done.')."\n";

<?php

$map = array(
  'PhabricatorAuthProviderOAuthAmazon' => 'PhabricatorAmazonAuthProvider',
  'PhabricatorAuthProviderOAuthAsana' => 'PhabricatorAsanaAuthProvider',
  'PhabricatorAuthProviderOAuth1Bitbucket'
    => 'PhabricatorBitbucketAuthProvider',
  'PhabricatorAuthProviderOAuthDisqus' => 'PhabricatorDisqusAuthProvider',
  'PhabricatorAuthProviderOAuthFacebook' => 'PhabricatorFacebookAuthProvider',
  'PhabricatorAuthProviderOAuthGitHub' => 'PhabricatorGitHubAuthProvider',
  'PhabricatorAuthProviderOAuthGoogle' => 'PhabricatorGoogleAuthProvider',
  'PhabricatorAuthProviderOAuth1JIRA' => 'PhabricatorJIRAAuthProvider',
  'PhabricatorAuthProviderLDAP' => 'PhabricatorLDAPAuthProvider',
  'PhabricatorAuthProviderPassword' => 'PhabricatorPasswordAuthProvider',
  'PhabricatorAuthProviderPersona' => 'PhabricatorPersonaAuthProvider',
  'PhabricatorAuthProviderOAuthTwitch' => 'PhabricatorTwitchAuthProvider',
  'PhabricatorAuthProviderOAuth1Twitter' => 'PhabricatorTwitterAuthProvider',
  'PhabricatorAuthProviderOAuthWordPress' => 'PhabricatorWordPressAuthProvider',
);

echo pht('Migrating auth providers...')."\n";
$table = new PhabricatorAuthProviderConfig();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $provider) {
  $provider_class = $provider->getProviderClass();

  queryfx(
    $conn_w,
    'UPDATE %T SET providerClass = %s WHERE id = %d',
    $provider->getTableName(),
    idx($map, $provider_class, $provider_class),
    $provider->getID());
}

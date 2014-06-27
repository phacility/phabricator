<?php

final class PhabricatorApplicationPassphrase extends PhabricatorApplication {

  public function getBaseURI() {
    return '/passphrase/';
  }

  public function getShortDescription() {
    return pht('Credential Store');
  }

  public function getIconName() {
    return 'passphrase';
  }

  public function getTitleGlyph() {
    return "\xE2\x97\x88";
  }

  public function getFlavorText() {
    return pht('Put your secrets in a lockbox.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/K(?P<id>\d+)' => 'PassphraseCredentialViewController',
      '/passphrase/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PassphraseCredentialListController',
        'create/' => 'PassphraseCredentialCreateController',
        'edit/(?:(?P<id>\d+)/)?' => 'PassphraseCredentialEditController',
        'destroy/(?P<id>\d+)/' => 'PassphraseCredentialDestroyController',
        'reveal/(?P<id>\d+)/' => 'PassphraseCredentialRevealController',
        'public/(?P<id>\d+)/' => 'PassphraseCredentialPublicController',
        'lock/(?P<id>\d+)/' => 'PassphraseCredentialLockController',
      ));
  }

  public function getRemarkupRules() {
    return array(
      new PassphraseRemarkupRule(),
    );
  }

}

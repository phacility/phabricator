<?php

final class PhabricatorPassphraseApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Passphrase');
  }

  public function getBaseURI() {
    return '/passphrase/';
  }

  public function getShortDescription() {
    return pht('Credential Store');
  }

  public function getIcon() {
    return 'fa-user-secret';
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
        'conduit/(?P<id>\d+)/' => 'PassphraseCredentialConduitController',
      ),
    );
  }

  public function getRemarkupRules() {
    return array(
      new PassphraseRemarkupRule(),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      PassphraseCredentialPHIDType::TYPECONST,
    );
  }

  protected function getCustomCapabilities() {
    $policy_key = id(new PassphraseCredentialAuthorPolicyRule())
      ->getObjectPolicyFullKey();

    return array(
      PassphraseDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created credentials.'),
        'template' => PassphraseCredentialPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
        'default' => $policy_key,
      ),
      PassphraseDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created credentials.'),
        'template' => PassphraseCredentialPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
        'default' => $policy_key,
      ),
    );
  }

}

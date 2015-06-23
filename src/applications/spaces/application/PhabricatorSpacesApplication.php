<?php

final class PhabricatorSpacesApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/spaces/';
  }

  public function getName() {
    return pht('Spaces');
  }

  public function getShortDescription() {
    return pht('Policy Namespaces');
  }

  public function getFontIcon() {
    return 'fa-th-large';
  }

  public function getTitleGlyph() {
    return "\xE2\x97\x8B";
  }

  public function getFlavorText() {
    return pht('Control access to groups of objects.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function canUninstall() {
    return true;
  }

  public function isPrototype() {
    return true;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Spaces User Guide'),
        'href' => PhabricatorEnv::getDoclink('Spaces User Guide'),
      ),
    );
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorSpacesRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/S(?P<id>[1-9]\d*)' => 'PhabricatorSpacesViewController',
      '/spaces/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorSpacesListController',
        'create/' => 'PhabricatorSpacesEditController',
        'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorSpacesEditController',
        '(?P<action>activate|archive)/(?P<id>\d+)/'
          => 'PhabricatorSpacesArchiveController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorSpacesCapabilityCreateSpaces::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      PhabricatorSpacesCapabilityDefaultView::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created spaces.'),
        'template' => PhabricatorSpacesNamespacePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      PhabricatorSpacesCapabilityDefaultEdit::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created spaces.'),
        'default' => PhabricatorPolicies::POLICY_ADMIN,
        'template' => PhabricatorSpacesNamespacePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }

}

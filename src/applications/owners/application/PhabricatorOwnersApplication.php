<?php

final class PhabricatorOwnersApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Owners');
  }

  public function getBaseURI() {
    return '/owners/';
  }

  public function getIcon() {
    return 'fa-gift';
  }

  public function getShortDescription() {
    return pht('Own Source Code');
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x81";
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Owners User Guide'),
        'href' => PhabricatorEnv::getDoclink('Owners User Guide'),
      ),
    );
  }

  public function getFlavorText() {
    return pht('Adopt today!');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorOwnersPackageRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/owners/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorOwnersListController',
        'new/' => 'PhabricatorOwnersEditController',
        'package/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersDetailController',
        'archive/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersArchiveController',
        'paths/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersPathsController',

        $this->getEditRoutePattern('edit/')
          => 'PhabricatorOwnersEditController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorOwnersDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created packages.'),
        'template' => PhabricatorOwnersPackagePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      PhabricatorOwnersDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created packages.'),
        'template' => PhabricatorOwnersPackagePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }

}

<?php

final class PhabricatorNuanceApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Nuance');
  }

  public function getIcon() {
    return 'fa-fax';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x8E";
  }

  public function isPrototype() {
    return true;
  }

  public function isLaunchable() {
    // Try to hide this even more for now.
    return false;
  }

  public function canUninstall() {
    return true;
  }

  public function getBaseURI() {
    return '/nuance/';
  }

  public function getShortDescription() {
    return pht('High-Volume Task Queues');
  }

  public function getRoutes() {
    return array(
      '/nuance/' => array(
        '' => 'NuanceConsoleController',
        'item/' => array(
          $this->getQueryRoutePattern() => 'NuanceItemListController',
          'view/(?P<id>[1-9]\d*)/' => 'NuanceItemViewController',
          'manage/(?P<id>[1-9]\d*)/' => 'NuanceItemManageController',
          'action/(?P<id>[1-9]\d*)/(?P<action>[^/]+)/'
            => 'NuanceItemActionController',
        ),
        'source/' => array(
          $this->getQueryRoutePattern() => 'NuanceSourceListController',
          $this->getEditRoutePattern('edit/') => 'NuanceSourceEditController',
          'view/(?P<id>[1-9]\d*)/' => 'NuanceSourceViewController',
        ),
        'queue/' => array(
          $this->getQueryRoutePattern() => 'NuanceQueueListController',
          $this->getEditRoutePattern('edit/') => 'NuanceQueueEditController',
          'view/(?P<id>[1-9]\d*)/' => 'NuanceQueueViewController',
        ),
      ),
      '/action/' => array(
        '(?P<id>[1-9]\d*)/(?P<path>.*)' => 'NuanceSourceActionController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      NuanceSourceDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created sources.'),
        'template' => NuanceSourcePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      NuanceSourceDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created sources.'),
        'template' => NuanceSourcePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
      NuanceSourceManageCapability::CAPABILITY => array(),
    );
  }

}

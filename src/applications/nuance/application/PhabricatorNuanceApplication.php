<?php

final class PhabricatorNuanceApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Nuance');
  }

  public function getIconName() {
    return 'nuance';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x8E";
  }

  public function isBeta() {
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
        'item/' => array(
          'view/(?P<id>[1-9]\d*)/' => 'NuanceItemViewController',
          'edit/(?P<id>[1-9]\d*)/' => 'NuanceItemEditController',
          'new/'                   => 'NuanceItemEditController',
        ),
        'source/' => array(
          'view/(?P<id>[1-9]\d*)/' => 'NuanceSourceViewController',
          'edit/(?P<id>[1-9]\d*)/' => 'NuanceSourceEditController',
          'new/'                   => 'NuanceSourceEditController',
        ),
        'queue/' => array(
          'view/(?P<id>[1-9]\d*)/' => 'NuanceQueueViewController',
          'edit/(?P<id>[1-9]\d*)/' => 'NuanceQueueEditController',
          'new/'                   => 'NuanceQueueEditController',
        ),
        'requestor/' => array(
          'view/(?P<id>[1-9]\d*)/' => 'NuanceRequestorViewController',
          'edit/(?P<id>[1-9]\d*)/' => 'NuanceRequestorEditController',
          'new/'                   => 'NuanceRequestorEditController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      NuanceSourceDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created sources.'),
      ),
      NuanceSourceDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created sources.'),
      ),
      NuanceSourceManageCapability::CAPABILITY => array(),
    );
  }

}

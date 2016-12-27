<?php

final class PhabricatorApplicationProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'application';

  public function getMenuItemTypeIcon() {
    return 'fa-globe';
  }

  public function getMenuItemTypeName() {
    return pht('Application');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $app = $this->getApplication($config);
    if ($app) {
      return $app->getName();
    } else {
      return pht('(Uninstalled Application)');
    }
    return $app->getName();
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorDatasourceEditField())
        ->setKey('application')
        ->setLabel(pht('Application'))
        ->setDatasource(new PhabricatorApplicationDatasource())
        ->setSingleValue($config->getMenuItemProperty('application')),
    );
  }

  private function getApplication(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();
    $phid = $config->getMenuItemProperty('application');
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    return $app;
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();
    $app = $this->getApplication($config);
    if (!$app) {
      return array();
    }

    $is_installed = PhabricatorApplication::isClassInstalledForViewer(
      get_class($app),
      $viewer);
    if (!$is_installed) {
      return array();
    }

    $item = $this->newItem()
      ->setHref($app->getApplicationURI())
      ->setName($app->getName())
      ->setIcon($app->getIcon());

    return array(
      $item,
    );
  }

}

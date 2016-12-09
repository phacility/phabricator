<?php

final class PhabricatorApplicationProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'application';

  public function getPanelTypeIcon() {
    return 'fa-globe';
  }

  public function getPanelTypeName() {
    return pht('Application');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config) {
    $app = $this->getApplication($config);
    if ($app) {
      return $app->getName();
    } else {
      return pht('(Uninstalled Application)');
    }
    return $app->getName();
  }

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
    return array(
      id(new PhabricatorDatasourceEditField())
        ->setKey('application')
        ->setLabel(pht('Application'))
        ->setDatasource(new PhabricatorApplicationDatasource())
        ->setSingleValue($config->getPanelProperty('application')),
    );
  }

  private function getApplication(
    PhabricatorProfilePanelConfiguration $config) {
    $viewer = $this->getViewer();
    $phid = $config->getPanelProperty('application');
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    return $app;
  }

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {
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

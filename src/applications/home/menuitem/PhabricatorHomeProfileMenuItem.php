<?php

final class PhabricatorHomeProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'home.dashboard';

  public function getMenuItemTypeName() {
    return pht('Built-in Homepage');
  }

  private function getDefaultName() {
    return pht('Home');
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $default = $this->getDefaultName();
    return $this->getNameFromConfig($config, $default);
  }

  public function getMenuItemTypeIcon() {
    return 'fa-home';
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function newPageContent(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();

    return id(new PHUIHomeView())
      ->setViewer($viewer);
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setPlaceholder($this->getDefaultName())
        ->setValue($config->getMenuItemProperty('name')),
    );
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();

    $name = $this->getDisplayName($config);
    $icon = 'fa-home';
    $uri = $this->getItemViewURI($config);

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}

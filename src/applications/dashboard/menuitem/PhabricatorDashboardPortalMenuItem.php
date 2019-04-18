<?php

final class PhabricatorDashboardPortalMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'portal';

  public function getMenuItemTypeIcon() {
    return 'fa-pencil';
  }

  public function getDefaultName() {
    return pht('Manage Portal');
  }

  public function getMenuItemTypeName() {
    return pht('Manage Portal');
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $name = $config->getMenuItemProperty('name');

    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
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

    if (!$viewer->isLoggedIn()) {
      return array();
    }

    $uri = $this->getItemViewURI($config);
    $name = $this->getDisplayName($config);
    $icon = 'fa-pencil';

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

  public function newPageContent(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();
    $engine = $this->getEngine();
    $portal = $engine->getProfileObject();
    $controller = $engine->getController();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Manage Portal'));

    $edit_uri = urisprintf(
      '/portal/edit/%d/',
      $portal->getID());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $portal,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $controller->newCurtainView($portal)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Portal'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref($edit_uri));

    $timeline = $controller->newTimelineView()
      ->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $timeline,
        ));

    return $view;
  }


}

<?php

final class PhabricatorHomeApplication extends PhabricatorApplication {

  const DASHBOARD_DEFAULT = 'dashboard:default';

  public function getBaseURI() {
    return '/home/';
  }

  public function getName() {
    return pht('Home');
  }

  public function getShortDescription() {
    return pht('Command Center');
  }

  public function getFontIcon() {
    return 'fa-home';
  }

  public function getRoutes() {
    return array(
      '/' => 'PhabricatorHomeMainController',
      '/(?P<only>home)/' => 'PhabricatorHomeMainController',
      '/home/' => array(
        'create/' => 'PhabricatorHomeQuickCreateController',
      ),
    );
  }

  public function isLaunchable() {
    return false;
  }

  public function getApplicationOrder() {
    return 9;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $quick_create_items = $this->loadAllQuickCreateItems($user);
    $items = array();

    if ($user->isLoggedIn() &&
        $user->isUserActivated() &&
        $quick_create_items) {
      $create_id = celerity_generate_unique_node_id();
      Javelin::initBehavior(
        'aphlict-dropdown',
        array(
          'bubbleID' => $create_id,
          'dropdownID' => 'phabricator-quick-create-menu',
          'local' => true,
          'desktop' => true,
          'right' => true,
        ));

      $item = id(new PHUIListItemView())
        ->setName(pht('Create New...'))
        ->setIcon('fa-plus')
        ->addClass('core-menu-item')
        ->setHref('/home/create/')
        ->addSigil('quick-create-menu')
        ->setID($create_id)
        ->setAural(pht('Quick Create'))
        ->setOrder(300);
      $items[] = $item;
    }

    return $items;
  }

  public function loadAllQuickCreateItems(PhabricatorUser $viewer) {
    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->execute();

    $items = array();
    foreach ($applications as $application) {
      $app_items = $application->getQuickCreateItems($viewer);
      foreach ($app_items as $app_item) {
        $items[] = $app_item;
      }
    }

    return $items;
  }

  public function buildMainMenuExtraNodes(
    PhabricatorUser $viewer,
    PhabricatorController $controller = null) {

    $items = $this->loadAllQuickCreateItems($viewer);

    $view = null;
    if ($items) {
      $view = new PHUIListView();
      $view->newLabel(pht('Create New...'));
      foreach ($items as $item) {
        $view->addMenuItem($item);
      }

      return phutil_tag(
        'div',
        array(
          'id' => 'phabricator-quick-create-menu',
          'class' => 'phabricator-main-menu-dropdown phui-list-sidenav',
          'style' => 'display: none',
        ),
        $view);
    }
    return $view;
  }

}

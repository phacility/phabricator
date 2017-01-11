<?php

final class PhabricatorHomeApplication extends PhabricatorApplication {

  private $quickItems;
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

  public function getIcon() {
    return 'fa-home';
  }

  public function getRoutes() {
    return array(
      '/' => 'PhabricatorHomeMainController',
      '/(?P<only>home)/' => 'PhabricatorHomeMainController',
      '/home/' => array(
        'create/' => 'PhabricatorHomeQuickCreateController',
        'menu/' => array(
          '' => 'PhabricatorHomeMenuController',
          '(?P<type>global|personal)/item/' => $this->getProfileMenuRouting(
          'PhabricatorHomeMenuItemController'),
        ),
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

    $quick_items = $this->getQuickActionItems($user);
    if (!$quick_items) {
      return array();
    }

    $items = array();
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
      ->setName(pht('Quick Actions'))
      ->setIcon('fa-plus')
      ->addClass('core-menu-item')
      ->setHref('/home/create/')
      ->addSigil('quick-create-menu')
      ->setID($create_id)
      ->setAural(pht('Quick Actions'))
      ->setOrder(300);
    $items[] = $item;

    return $items;
  }

  public function buildMainMenuExtraNodes(
    PhabricatorUser $viewer,
    PhabricatorController $controller = null) {

    $items = $this->getQuickActionItems($viewer);

    $view = null;
    if ($items) {
      $view = new PHUIListView();
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

  private function getQuickActionItems(PhabricatorUser $viewer) {
    if ($this->quickItems === null) {
      $items = PhabricatorQuickActions::loadMenuItemsForUser($viewer);
      $this->quickItems = $items;
    }
    return $this->quickItems;
  }

}

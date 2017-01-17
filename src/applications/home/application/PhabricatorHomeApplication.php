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

  public function getIcon() {
    return 'fa-home';
  }

  public function getRoutes() {
    return array(
      '/' => 'PhabricatorHomeMainController',
      '/(?P<only>home)/' => 'PhabricatorHomeMainController',
      '/home/' => array(
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

  public function buildMainMenuExtraNodes(
    PhabricatorUser $viewer,
    PhabricatorController $controller = null) {

    if (!$viewer->isLoggedIn()) {
      return;
    }

    $image = $viewer->getProfileImageURI();

    $profile_image = id(new PHUIIconView())
      ->setImage($image)
      ->setHeadSize(PHUIIconView::HEAD_SMALL);

    if ($controller) {
      $application = $controller->getCurrentApplication();
    } else {
      $application = null;
    }
    $dropdown_menu = $this->renderUserDropdown($viewer, $application);

    $menu_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'user-menu',
      array(
        'menuID' => $menu_id,
        'menu' => $dropdown_menu->getDropdownMenuMetadata(),
      ));

    return id(new PHUIButtonView())
      ->setID($menu_id)
      ->setTag('a')
      ->setHref('/p/'.$viewer->getUsername().'/')
      ->setIcon($profile_image)
      ->addClass('phabricator-core-user-menu')
      ->setHasCaret(true)
      ->setNoCSS(true);
  }

  private function renderUserDropdown(
    PhabricatorUser $viewer,
    $application) {

    $view = id(new PhabricatorActionListView())
      ->setViewer($viewer);

    // User Menu
    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($viewer->getRealName())
        ->setLabel(true));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Profile'))
        ->setHref('/p/'.$viewer->getUsername().'/'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Settings'))
        ->setHref('/settings/user/'.$viewer->getUsername().'/'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Manage'))
        ->setHref('/people/manage/'.$viewer->getID().'/'));

    // Help Menus
    if ($application) {
      $help_links = $application->getHelpMenuItems($viewer);
      if ($help_links) {
        foreach ($help_links as $link) {
          $view->addAction($link);
        }
      }
    }

    // Logout Menu
    $view->addAction(
      id(new PhabricatorActionView())
        ->addSigil('logout-item')
        ->setType(PhabricatorActionView::TYPE_DIVIDER));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Log Out %s', $viewer->getUsername()))
        ->addSigil('logout-item')
        ->setHref('/logout/')
        ->setWorkflow(true));

    return $view;
  }

}

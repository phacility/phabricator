<?php

final class PhabricatorHomeApplication extends PhabricatorApplication {

  private $application;

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
    if ($controller) {
      $this->application = $controller->getCurrentApplication();
    }

    $profile_image = id(new PHUIIconView())
      ->setImage($image)
      ->setHeadSize(PHUIIconView::HEAD_SMALL);

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setHref('/p/'.$viewer->getUsername().'/')
      ->setIcon($profile_image)
      ->addClass('phabricator-core-user-menu')
      ->setNoCSS(true)
      ->setDropdown(true)
      ->setDropdownMenu($this->renderUserDropdown($viewer));
  }

  private function renderUserDropdown(PhabricatorUser $viewer) {

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
    if ($this->application) {
      $application = $this->application;
      $help_links = $application->getHelpMenuItems($viewer);
      if ($help_links) {
        $view->addAction(
          id(new PhabricatorActionView())
            ->setType(PhabricatorActionView::TYPE_DIVIDER));

        foreach ($help_links as $link) {
          $link->setOpenInNewWindow(true);
          $view->addAction($link);
        }
      }
    }

    // Logout Menu
    $view->addAction(
      id(new PhabricatorActionView())
        ->setType(PhabricatorActionView::TYPE_DIVIDER));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Log Out %s', $viewer->getUsername()))
        ->setHref('/logout/')
        ->setWorkflow(true));

    return $view;
  }

}

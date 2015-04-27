<?php

final class PhabricatorHelpApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Help');
  }

  public function canUninstall() {
    return false;
  }

  public function isUnlisted() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/help/' => array(
        'keyboardshortcut/' => 'PhabricatorHelpKeyboardShortcutController',
        'editorprotocol/' => 'PhabricatorHelpEditorProtocolController',
        'documentation/(?P<application>\w+)/'
          => 'PhabricatorHelpDocumentationController',
      ),
    );
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $application = null;
    if ($controller) {
      $application = $controller->getCurrentApplication();
    }

    $items = array();

    $help_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'aphlict-dropdown',
      array(
        'bubbleID' => $help_id,
        'dropdownID' => 'phabricator-help-menu',
        'applicationClass' => 'PhabricatorHelpApplication',
        'local' => true,
        'desktop' => true,
        'right' => true,
      ));

    $item = id(new PHUIListItemView())
      ->setIcon('fa-life-ring')
      ->addClass('core-menu-item')
      ->setID($help_id)
      ->setOrder(200);

    $hide = true;
    if ($application) {
      $help_name = pht('%s Help', $application->getName());
        $item
          ->setName($help_name)
          ->setHref('/help/documentation/'.get_class($application).'/')
          ->setAural($help_name);
      $help_items = $application->getHelpMenuItems($user);
      if ($help_items) {
        $hide = false;
      }
    }
    if ($hide) {
      $item->setStyle('display: none');
    }
    $items[] = $item;

    return $items;
  }

  public function buildMainMenuExtraNodes(
    PhabricatorUser $viewer,
    PhabricatorController $controller = null) {

    $application = null;
    if ($controller) {
      $application = $controller->getCurrentApplication();
    }

    $view = null;
    if ($application) {
      $help_items = $application->getHelpMenuItems($viewer);
      if ($help_items) {
        $view = new PHUIListView();
        foreach ($help_items as $item) {
          $view->addMenuItem($item);
        }
      }
    }

    return phutil_tag(
      'div',
      array(
        'id' => 'phabricator-help-menu',
        'class' => 'phabricator-main-menu-dropdown phui-list-sidenav',
        'style' => 'display: none',
      ),
      $view);
  }

}

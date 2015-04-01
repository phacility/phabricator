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
    if ($application) {
      $help_items = $application->getHelpMenuItems($user);
      if ($help_items) {
        $help_id = celerity_generate_unique_node_id();

        Javelin::initBehavior(
          'aphlict-dropdown',
          array(
            'bubbleID' => $help_id,
            'dropdownID' => 'phabricator-help-menu',
            'local' => true,
            'desktop' => true,
            'right' => true,
          ));

        $help_name = pht('%s Help', $application->getName());

        $item = id(new PHUIListItemView())
          ->setName($help_name)
          ->setIcon('fa-life-ring')
          ->setHref('/help/documentation/'.get_class($application).'/')
          ->addClass('core-menu-item')
          ->setID($help_id)
          ->setAural($help_name)
          ->setOrder(200);
        $items[] = $item;
      }
    }

    return $items;
  }

  public function buildMainMenuExtraNodes(
    PhabricatorUser $viewer,
    PhabricatorController $controller = null) {

    if (!$controller) {
      return null;
    }

    $application = $controller->getCurrentApplication();
    if (!$application) {
      return null;
    }

    $help_items = $application->getHelpMenuItems($viewer);
    if (!$help_items) {
      return null;
    }

    $view = new PHUIListView();
    foreach ($help_items as $item) {
      $view->addMenuItem($item);
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

<?php

final class PhabricatorApplicationHelp extends PhabricatorApplication {

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
      ),
    );
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    $application = null;
    if ($controller) {
      $application = $controller->getCurrentApplication();
    }

    if ($application && $application->getHelpURI()) {
      $help_name = pht('%s Help', $application->getName());

      $item = id(new PHUIListItemView())
        ->setName($help_name)
        ->addClass('core-menu-item')
        ->setIcon('info-sm')
        ->setAural($help_name)
        ->setOrder(200)
        ->setHref($application->getHelpURI());
      $items[] = $item;
    }

    return $items;
  }

}

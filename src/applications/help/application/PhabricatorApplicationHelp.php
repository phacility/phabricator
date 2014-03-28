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
      $item = id(new PHUIListItemView())
        ->setName(pht('%s Help', $application->getName()))
        ->addClass('core-menu-item')
        ->setIcon('info-sm')
        ->setOrder(200)
        ->setHref($application->getHelpURI());
      $items[] = $item;
    }

    return $items;
  }

}

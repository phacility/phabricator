<?php

final class PhabricatorHelpMainMenuBarExtension
  extends PhabricatorMainMenuBarExtension {

  const MAINMENUBARKEY = 'help';

  public function isExtensionEnabledForViewer(PhabricatorUser $viewer) {
    return true;
  }

  public function buildMainMenus() {
    $application = $this->getApplication();
    if (!$application) {
      return array();
    }

    $viewer = $this->getViewer();
    $help_links = $application->getHelpMenuItems($viewer);
    if (!$help_links) {
      return array();
    }

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

    $help_item = id(new PHUIListItemView())
      ->setIcon('fa-life-ring')
      ->addClass('core-menu-item')
      ->setID($help_id)
      ->setName($help_name)
      ->setHref('/help/documentation/'.get_class($application).'/')
      ->setAural($help_name);

    $view = new PHUIListView();
    foreach ($help_links as $help_link) {
      $view->addMenuItem($help_link);
    }

    $dropdown_menu = phutil_tag(
      'div',
      array(
        'id' => 'phabricator-help-menu',
        'class' => 'phabricator-main-menu-dropdown phui-list-sidenav',
        'style' => 'display: none',
      ),
      $view);

    $help_menu = id(new PHUIMainMenuView())
      ->setOrder(200)
      ->setMenuBarItem($help_item)
      ->appendChild($dropdown_menu);

    return array(
      $help_menu,
    );
  }

}

<?php

final class PhabricatorApplicationDiviner extends PhabricatorApplication {

  public function getBaseURI() {
    return '/diviner/';
  }

  public function getIconName() {
    return 'diviner';
  }

  public function getShortDescription() {
    return pht('Documentation');
  }

  public function getTitleGlyph() {
    return "\xE2\x97\x89";
  }

  public function getRoutes() {
    return array(
      '/diviner/' => array(
        '' => 'DivinerMainController',
        'query/((?<key>[^/]+)/)?' => 'DivinerAtomListController',
        'find/' => 'DivinerFindController',
      ),
      '/book/(?P<book>[^/]+)/' => 'DivinerBookController',
      '/book/'.
        '(?P<book>[^/]+)/'.
        '(?P<type>[^/]+)/'.
        '(?:(?P<context>[^/]+)/)?'.
        '(?P<name>[^/]+)/'.
        '(?:(?P<index>\d+)/)?' => 'DivinerAtomController',
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function getRemarkupRules() {
    return array(
      new DivinerRemarkupRuleSymbol(),
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

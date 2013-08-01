<?php

final class PhabricatorApplicationDiviner extends PhabricatorApplication {

  public function getBaseURI() {
    return '/diviner/';
  }

  public function getIconName() {
    return 'diviner';
  }

  public function getShortDescription() {
    return 'Documentation';
  }

  public function getTitleGlyph() {
    return "\xE2\x97\x89";
  }

  public function getRoutes() {
    return array(
      '/diviner/' => array(
        '' => 'DivinerLegacyController',
        'query/((?<key>[^/]+)/)?' => 'DivinerAtomListController',
        'find/' => 'DivinerFindController',
      ),
      '/docs/(?P<keyword>[^/]+)/' => 'DivinerJumpController',
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
      $item = new PHUIListItemView();
      $item->setName(pht('%s Help', $application->getName()));
      $item->addClass('core-menu-item');
      $item->setIcon('help');
      $item->setHref($application->getHelpURI());
      $items[] = $item;
    }

    return $items;
  }


}


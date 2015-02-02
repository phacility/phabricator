<?php

final class PhabricatorFlagsApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Flags');
  }

  public function getShortDescription() {
    return pht('Personal Bookmarks');
  }

  public function getBaseURI() {
    return '/flag/';
  }

  public function getFontIcon() {
    return 'fa-flag';
  }

  public function getEventListeners() {
    return array(
      new PhabricatorFlagsUIEventListener(),
    );
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\x90";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $flags = id(new PhabricatorFlagQuery())
      ->setViewer($user)
      ->withOwnerPHIDs(array($user->getPHID()))
      ->setLimit(self::MAX_STATUS_ITEMS)
      ->execute();

    $count = count($flags);
    $count_str = self::formatStatusCount(
      $count,
      '%s Flagged Objects',
      '%d Flagged Object(s)');
    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText($count_str)
      ->setCount($count);

    return $status;
  }

  public function getRoutes() {
    return array(
      '/flag/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorFlagListController',
        'view/(?P<view>[^/]+)/' => 'PhabricatorFlagListController',
        'edit/(?P<phid>[^/]+)/' => 'PhabricatorFlagEditController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorFlagDeleteController',
      ),
    );
  }

}

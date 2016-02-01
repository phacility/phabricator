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

  public function getIcon() {
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
    $limit = self::MAX_STATUS_ITEMS;

    $flags = id(new PhabricatorFlagQuery())
      ->setViewer($user)
      ->withOwnerPHIDs(array($user->getPHID()))
      ->setLimit(self::MAX_STATUS_ITEMS)
      ->execute();

    $count = count($flags);
    if ($count >= $limit) {
      $count_str = pht('%s+ Flagged Object(s)', new PhutilNumber($limit - 1));
    } else {
      $count_str = pht('%s Flagged Object(s)', new PhutilNumber($count));
    }

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

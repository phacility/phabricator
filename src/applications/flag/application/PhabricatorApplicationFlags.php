<?php

final class PhabricatorApplicationFlags extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Personal Bookmarks');
  }

  public function getBaseURI() {
    return '/flag/';
  }

  public function getIconName() {
    return 'flags';
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
      ->execute();

    $count = count($flags);
    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Flagged Object(s)', $count))
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

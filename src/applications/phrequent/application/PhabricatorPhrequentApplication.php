<?php

final class PhabricatorPhrequentApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phrequent');
  }

  public function getShortDescription() {
    return pht('Track Time Spent');
  }

  public function getBaseURI() {
    return '/phrequent/';
  }

  public function isPrototype() {
    return true;
  }

  public function getFontIcon() {
    return 'fa-clock-o';
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getApplicationOrder() {
    return 0.110;
  }

  public function getEventListeners() {
    return array(
      new PhrequentUIEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/phrequent/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhrequentListController',
        'track/(?P<verb>[a-z]+)/(?P<phid>[^/]+)/'
          => 'PhrequentTrackController',
      ),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    // Show number of objects that are currently
    // being tracked for a user.

    $count = PhrequentUserTimeQuery::getUserTotalObjectsTracked(
      $user,
      self::MAX_STATUS_ITEMS);
    $count_str = self::formatStatusCount(
      $count,
      '%s Objects Tracked',
      '%d Object(s) Tracked');
    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText($count_str)
      ->setCount($count);

    return $status;
  }

}

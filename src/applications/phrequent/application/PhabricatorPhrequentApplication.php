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

  public function getIcon() {
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
    $limit = self::MAX_STATUS_ITEMS;

    // Show number of objects that are currently
    // being tracked for a user.

    $count = PhrequentUserTimeQuery::getUserTotalObjectsTracked($user, $limit);
    if ($count >= $limit) {
      $count_str = pht('%s+ Object(s) Tracked', new PhutilNumber($limit - 1));
    } else {
      $count_str = pht('%s Object(s) Tracked', new PhutilNumber($count));
    }

    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText($count_str)
      ->setCount($count);

    return $status;
  }

}

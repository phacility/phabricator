<?php

final class PhabricatorApplicationPhrequent extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Track Time');
  }

  public function getBaseURI() {
    return '/phrequent/';
  }

  public function isBeta() {
    return true;
  }

  public function getIconName() {
    return 'phrequent';
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
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
        '' => 'PhrequentListController',
        'view/(?P<view>\w+)/' => 'PhrequentListController',
        'track/(?P<verb>[a-z]+)/(?P<phid>[^/]+)/'
          => 'PhrequentTrackController'
      ),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    // Show number of objects that are currently
    // being tracked for a user.

    $count = PhrequentUserTimeQuery::getUserTotalObjectsTracked($user);
    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Object(s) Tracked', $count))
      ->setCount($count);

    return $status;
  }

}


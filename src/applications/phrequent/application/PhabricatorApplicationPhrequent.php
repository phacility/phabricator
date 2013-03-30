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
        'track/(?P<verb>[a-z]+)/(?P<phid>[^/]+)/'
          => 'PhrequentTrackController'
      ),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    // TODO: Show number of timers that are currently
    // running for a user.

    /*

    $query = id(new ManiphestTaskQuery())
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->withOwners(array($user->getPHID()))
      ->setLimit(1)
      ->setCalculateRows(true);
    $query->execute();

    $count = $query->getRowCount();
    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Assigned Task(s)', $count))
      ->setCount($count);

    */

    return $status;
  }

}


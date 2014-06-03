<?php

final class PhabricatorApplicationNotifications extends PhabricatorApplication {

  public function getBaseURI() {
    return '/notification/';
  }

  public function getShortDescription() {
    return pht('Real-Time Updates and Alerts');
  }

  public function getRoutes() {
    return array(
      '/notification/' => array(
        '(?:(?P<filter>all|unread)/)?'
          => 'PhabricatorNotificationListController',
        'panel/' => 'PhabricatorNotificationPanelController',
        'individual/' => 'PhabricatorNotificationIndividualController',
        'status/' => 'PhabricatorNotificationStatusController',
        'clear/' => 'PhabricatorNotificationClearController',
        'test/' => 'PhabricatorNotificationTestController',
      ),
    );
  }

  public function isLaunchable() {
    return false;
  }

}

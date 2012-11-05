<?php

final class PhabricatorApplicationSubscriptions extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function getEventListeners() {
    return array(
      new PhabricatorSubscriptionsUIEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/subscriptions/' => array(
        '(?P<action>add|delete)/'.
        '(?P<phid>[^/]+)/' => 'PhabricatorSubscriptionsEditController',
      ),
    );
  }

}


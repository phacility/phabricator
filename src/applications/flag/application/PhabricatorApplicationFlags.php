<?php

final class PhabricatorApplicationFlags extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Reminders';
  }

  public function getBaseURI() {
    return '/flag/';
  }

  public function getAutospriteName() {
    return 'flags';
  }

  public function getEventListeners() {
    return array(
      new PhabricatorFlagsUIEventListener(),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $flags = id(new PhabricatorFlagQuery())
      ->withOwnerPHIDs(array($user->getPHID()))
      ->execute();

    $count = count($flags);
    $type = $count
      ? PhabricatorApplicationStatusView::TYPE_INFO
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Flagged Object(s)', $count))
      ->setCount($count);

    return $status;
  }

  public function getRoutes() {
    return array(
      '/flag/' => array(
        '' => 'PhabricatorFlagListController',
        'view/(?P<view>[^/]+)/' => 'PhabricatorFlagListController',
        'edit/(?P<phid>[^/]+)/' => 'PhabricatorFlagEditController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorFlagDeleteController',
      ),
    );
  }

}


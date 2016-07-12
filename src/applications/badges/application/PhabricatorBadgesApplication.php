<?php

final class PhabricatorBadgesApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Badges');
  }

  public function getBaseURI() {
    return '/badges/';
  }

  public function getShortDescription() {
    return pht('Achievements and Notority');
  }

  public function getIcon() {
    return 'fa-trophy';
  }

  public function getFlavorText() {
    return pht('Build self esteem through gamification.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function canUninstall() {
    return true;
  }

  public function isPrototype() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/badges/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorBadgesListController',
        'award/(?:(?P<id>\d+)/)?'
          => 'PhabricatorBadgesAwardController',
        'create/'
          => 'PhabricatorBadgesEditController',
        'comment/(?P<id>[1-9]\d*)/'
          => 'PhabricatorBadgesCommentController',
        $this->getEditRoutePattern('edit/')
            => 'PhabricatorBadgesEditController',
        'archive/(?:(?P<id>\d+)/)?'
          => 'PhabricatorBadgesArchiveController',
        'view/(?:(?P<id>\d+)/)?'
          => 'PhabricatorBadgesViewController',
        'recipients/(?P<id>[1-9]\d*)/'
          => 'PhabricatorBadgesEditRecipientsController',
        'recipients/(?P<id>[1-9]\d*)/remove/'
          => 'PhabricatorBadgesRemoveRecipientsController',

      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorBadgesCreateCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
        'caption' => pht('Default create policy for badges.'),
      ),
      PhabricatorBadgesDefaultEditCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
        'caption' => pht('Default edit policy for badges.'),
        'template' => PhabricatorBadgesPHIDType::TYPECONST,
      ),
    );
  }

}

<?php

final class TimeTrackerApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/timetracker/';
  }

  public function getShortDescription() {
    return pht('Time tracker');
  }

  public function getName() {
    return pht('Time Tracker');
  }

  public function getIcon() {
    return 'fa-clock-o';
  }
  
  public function isPinnedByDefault(PhabricatorUser $viewer) {
      return true;
  }

  public function getTitleGlyph() {
    return "\xE2\x8F\x9A";
  }

  public function getFlavorText() {
    return pht('A gallery of modern art.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isPrototype() {
    return false;
  }

  public function getApplicationOrder() {
    return 0.1;
  }

  public function getRoutes() {
    return array(
      '/timetracker/' => array(
        '' => 'TimeTrackerRenderController',
        'view/(?P<class>[^/]+)/' => 'TimeTrackerRenderController',
      ),
    );
  }

}

<?php

final class PhabricatorApplicationCalendar extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Dates and Stuff');
  }

  public function getFlavorText() {
    return pht('Never miss an episode ever again.');
  }

  public function getBaseURI() {
    return '/calendar/';
  }

  public function getIconName() {
    return 'calendar';
  }

  public function getTitleGlyph() {
    // Unicode has a calendar character but it's in some distant code plane,
    // use "keyboard" since it looks vaguely similar.
    return "\xE2\x8C\xA8";
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function isBeta() {
    return true;
  }

  public function getQuickCreateURI() {
    return $this->getBaseURI().'status/create/';
  }

  public function getRoutes() {
    return array(
      '/calendar/' => array(
        '' => 'PhabricatorCalendarBrowseController',
        'status/' => array(
          '' => 'PhabricatorCalendarViewStatusController',
          'create/' =>
            'PhabricatorCalendarEditStatusController',
          'delete/(?P<id>[1-9]\d*)/' =>
            'PhabricatorCalendarDeleteStatusController',
          'edit/(?P<id>[1-9]\d*)/' =>
            'PhabricatorCalendarEditStatusController',
          'view/(?P<phid>[^/]+)/' =>
            'PhabricatorCalendarViewStatusController',
        ),
      ),
    );
  }

}

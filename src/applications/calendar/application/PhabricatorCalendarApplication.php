<?php

final class PhabricatorCalendarApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Calendar');
  }

  public function getShortDescription() {
    return pht('Upcoming Events');
  }

  public function getFlavorText() {
    return pht('Never miss an episode ever again.');
  }

  public function getBaseURI() {
    return '/calendar/';
  }

  public function getIcon() {
    return 'fa-calendar';
  }

  public function getTitleGlyph() {
    // Unicode has a calendar character but it's in some distant code plane,
    // use "keyboard" since it looks vaguely similar.
    return "\xE2\x8C\xA8";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isPrototype() {
    return true;
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorCalendarRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/E(?P<id>[1-9]\d*)(?:/(?P<sequence>\d+)/)?'
        => 'PhabricatorCalendarEventViewController',
      '/calendar/' => array(
        '(?:query/(?P<queryKey>[^/]+)/(?:(?P<year>\d+)/'.
          '(?P<month>\d+)/)?(?:(?P<day>\d+)/)?)?'
          => 'PhabricatorCalendarEventListController',
        'event/' => array(
          $this->getEditRoutePattern('edit/')
            => 'PhabricatorCalendarEventEditController',
          'drag/(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarEventDragController',
          'cancel/(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarEventCancelController',
          '(?P<action>join|decline|accept)/(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarEventJoinController',
          'export/(?P<id>[1-9]\d*)/(?P<filename>[^/]*)'
            => 'PhabricatorCalendarEventExportController',
        ),
        'export/' => array(
          $this->getQueryRoutePattern()
            => 'PhabricatorCalendarExportListController',
          $this->getEditRoutePattern('edit/')
            => 'PhabricatorCalendarExportEditController',
          '(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarExportViewController',
          'ics/(?P<secretKey>[^/]+)/(?P<filename>[^/]*)'
            => 'PhabricatorCalendarExportICSController',
          'disable/(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarExportDisableController',

        ),
      ),
    );
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Calendar User Guide'),
        'href' => PhabricatorEnv::getDoclink('Calendar User Guide'),
      ),
    );
  }

  public function getMailCommandObjects() {
    return array(
      'event' => array(
        'name' => pht('Email Commands: Events'),
        'header' => pht('Interacting with Calendar Events'),
        'object' => new PhabricatorCalendarEvent(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'events in Calendar. These commands work when creating new tasks '.
          'via email and when replying to existing tasks.'),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorCalendarEventDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created events.'),
        'template' => PhabricatorCalendarEventPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      PhabricatorCalendarEventDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created events.'),
        'template' => PhabricatorCalendarEventPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }

}

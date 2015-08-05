<?php

final class PhabricatorCalendarEventSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $calendarYear;
  private $calendarMonth;
  private $calendarDay;

  public function getResultTypeDescription() {
    return pht('Calendar Events');
  }

  public function getApplicationClassName() {
    return 'PhabricatorCalendarApplication';
  }

  public function newQuery() {
    return new PhabricatorCalendarEventQuery();
  }

  protected function shouldShowOrderField() {
    return false;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Created By'))
        ->setKey('creatorPHIDs')
        ->setDatasource(new PhabricatorPeopleUserFunctionDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Invited'))
        ->setKey('invitedPHIDs')
        ->setDatasource(new PhabricatorPeopleUserFunctionDatasource()),
      id(new PhabricatorSearchDateControlField())
        ->setLabel(pht('Occurs After'))
        ->setKey('rangeStart'),
      id(new PhabricatorSearchDateControlField())
        ->setLabel(pht('Occurs Before'))
        ->setKey('rangeEnd')
        ->setAliases(array('rangeEnd')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('upcoming')
        ->setOptions(array(
          'upcoming' => pht('Show only upcoming events.'),
          )),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Cancelled Events'))
        ->setKey('isCancelled')
        ->setOptions($this->getCancelledOptions())
        ->setDefault('active'),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Display Options'))
        ->setKey('display')
        ->setOptions($this->getViewOptions())
        ->setDefault('month'),
    );
  }

  private function getCancelledOptions() {
    return array(
      'active' => pht('Active Events Only'),
      'cancelled' => pht('Cancelled Events Only'),
      'both' => pht('Both Cancelled and Active Events'),
    );
  }

  private function getViewOptions() {
    return array(
      'month' => pht('Month View'),
      'day' => pht('Day View'),
      'list'   => pht('List View'),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    $viewer = $this->requireViewer();

    if ($map['creatorPHIDs']) {
      $query->withCreatorPHIDs($map['creatorPHIDs']);
    }

    if ($map['invitedPHIDs']) {
      $query->withInvitedPHIDs($map['invitedPHIDs']);
    }

    $range_start = $map['rangeStart'];
    $range_end = $map['rangeEnd'];
    $display = $map['display'];

    if ($map['upcoming'] && $map['upcoming'][0] == 'upcoming') {
      $upcoming = true;
    } else {
      $upcoming = false;
    }

    list($range_start, $range_end) = $this->getQueryDateRange(
      $range_start,
      $range_end,
      $display,
      $upcoming);

    $query->withDateRange($range_start, $range_end);

    switch ($map['isCancelled']) {
      case 'active':
        $query->withIsCancelled(false);
        break;
      case 'cancelled':
        $query->withIsCancelled(true);
        break;
    }

    return $query->setGenerateGhosts(true);
  }

  private function getQueryDateRange(
    $start_date_wild,
    $end_date_wild,
    $display,
    $upcoming) {

    $start_date_value = $this->getSafeDate($start_date_wild);
    $end_date_value = $this->getSafeDate($end_date_wild);

    $viewer = $this->requireViewer();
    $timezone = new DateTimeZone($viewer->getTimezoneIdentifier());
    $min_range = null;
    $max_range = null;

    $min_range = $start_date_value->getEpoch();
    $max_range = $end_date_value->getEpoch();

    if ($display == 'month' || $display == 'day') {
      list($start_year, $start_month, $start_day) =
        $this->getDisplayYearAndMonthAndDay($min_range, $max_range, $display);

      $start_day = new DateTime(
        "{$start_year}-{$start_month}-{$start_day}",
        $timezone);
      $next = clone $start_day;

      if ($display == 'month') {
        $next->modify('+1 month');
      } else if ($display == 'day') {
        $next->modify('+7 day');
      }

      $display_start = $start_day->format('U');
      $display_end = $next->format('U');

      $preferences = $viewer->loadPreferences();
      $pref_week_day = PhabricatorUserPreferences::PREFERENCE_WEEK_START_DAY;

      $start_of_week = $preferences->getPreference($pref_week_day, 0);
      $end_of_week = ($start_of_week + 6) % 7;

      $first_of_month = $start_day->format('w');
      $last_of_month = id(clone $next)->modify('-1 day')->format('w');

      if (!$min_range || ($min_range < $display_start)) {
        $min_range = $display_start;

        if ($display == 'month' &&
          $first_of_month !== $start_of_week) {
          $interim_day_num = ($first_of_month + 7 - $start_of_week) % 7;
          $min_range = id(clone $start_day)
            ->modify('-'.$interim_day_num.' days')
            ->format('U');
        }
      }
      if (!$max_range || ($max_range > $display_end)) {
        $max_range = $display_end;

        if ($display == 'month' &&
          $last_of_month !== $end_of_week) {
          $interim_day_num = ($end_of_week + 7 - $last_of_month) % 7;
          $max_range = id(clone $next)
            ->modify('+'.$interim_day_num.' days')
            ->format('U');
        }
      }
    }

    if ($upcoming) {
      if ($min_range) {
        $min_range = max(time(), $min_range);
      } else {
        $min_range = time();
      }
    }

    return array($min_range, $max_range);
  }

  protected function getURI($path) {
    return '/calendar/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'month' => pht('Month View'),
      'day' => pht('Day View'),
      'upcoming' => pht('Upcoming Events'),
      'all' => pht('All Events'),
    );

    return $names;
  }

  public function setCalendarYearAndMonthAndDay($year, $month, $day = null) {
    $this->calendarYear = $year;
    $this->calendarMonth = $month;
    $this->calendarDay = $day;

    return $this;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'month':
        return $query->setParameter('display', 'month');
      case 'day':
        return $query->setParameter('display', 'day');
      case 'upcoming':
        return $query
          ->setParameter('display', 'list')
          ->setParameter('upcoming', array(
            0 => 'upcoming',
          ));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $objects,
    PhabricatorSavedQuery $query) {
    $phids = array();
    foreach ($objects as $event) {
      $phids[$event->getUserPHID()] = 1;
    }
    return array_keys($phids);
  }

  protected function renderResultList(
    array $events,
    PhabricatorSavedQuery $query,
    array $handles) {

    if ($this->isMonthView($query)) {
      return $this->buildCalendarView($events, $query, $handles);
    } else if ($this->isDayView($query)) {
      return $this->buildCalendarDayView($events, $query, $handles);
    }

    assert_instances_of($events, 'PhabricatorCalendarEvent');
    $viewer = $this->requireViewer();
    $list = new PHUIObjectItemListView();

    foreach ($events as $event) {
      $duration = '';
      $event_date_info = $this->getEventDateLabel($event);
      $creator_handle = $handles[$event->getUserPHID()];
      $attendees = array();

      foreach ($event->getInvitees() as $invitee) {
        $attendees[] = $invitee->getInviteePHID();
      }

      $attendees = pht(
        'Attending: %s',
        $viewer->renderHandleList($attendees)
          ->setAsInline(1)
          ->render());

      if (strlen($event->getDuration()) > 0) {
        $duration = pht(
          'Duration: %s',
          $event->getDuration());
      }

      if ($event->getIsGhostEvent()) {
        $title_text = $event->getMonogram()
          .' ('
          .$event->getSequenceIndex()
          .'): '
          .$event->getName();
      } else {
        $title_text = $event->getMonogram().': '.$event->getName();
      }

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($event)
        ->setHeader($title_text)
        ->setHref($event->getURI())
        ->addAttribute($event_date_info)
        ->addAttribute($attendees)
        ->addIcon('none', $duration);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No events found.'));

    return $result;
  }

  private function buildCalendarView(
    array $statuses,
    PhabricatorSavedQuery $query,
    array $handles) {

    $viewer = $this->requireViewer();
    $now = time();

    list($start_year, $start_month) =
      $this->getDisplayYearAndMonthAndDay(
        $this->getQueryDateFrom($query)->getEpoch(),
        $this->getQueryDateTo($query)->getEpoch(),
        $query->getParameter('display'));

    $now_year  = phabricator_format_local_time($now, $viewer, 'Y');
    $now_month = phabricator_format_local_time($now, $viewer, 'm');
    $now_day   = phabricator_format_local_time($now, $viewer, 'j');

    if ($start_month == $now_month && $start_year == $now_year) {
      $month_view = new PHUICalendarMonthView(
        $this->getQueryDateFrom($query),
        $this->getQueryDateTo($query),
        $start_month,
        $start_year,
        $now_day);
    } else {
      $month_view = new PHUICalendarMonthView(
        $this->getQueryDateFrom($query),
        $this->getQueryDateTo($query),
        $start_month,
        $start_year);
    }

    $month_view->setUser($viewer);

    $phids = mpull($statuses, 'getUserPHID');

    foreach ($statuses as $status) {
      $viewer_is_invited = $status->getIsUserInvited($viewer->getPHID());

      $event = new AphrontCalendarEventView();
      $event->setEpochRange($status->getDateFrom(), $status->getDateTo());
      $event->setIsAllDay($status->getIsAllDay());
      $event->setIcon($status->getIcon());

      $name_text = $handles[$status->getUserPHID()]->getName();
      $status_text = $status->getName();
      $event->setUserPHID($status->getUserPHID());
      $event->setDescription(pht('%s (%s)', $name_text, $status_text));
      $event->setName($status_text);
      $event->setURI($status->getURI());
      $event->setViewerIsInvited($viewer_is_invited);
      $month_view->addEvent($event);
    }

    $month_view->setBrowseURI(
      $this->getURI('query/'.$query->getQueryKey().'/'));

    // TODO redesign-2015 : Move buttons out of PHUICalendarView?
    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($month_view);

    return $result;
  }

  private function buildCalendarDayView(
    array $statuses,
    PhabricatorSavedQuery $query,
    array $handles) {

    $viewer = $this->requireViewer();

    list($start_year, $start_month, $start_day) =
      $this->getDisplayYearAndMonthAndDay(
        $this->getQueryDateFrom($query)->getEpoch(),
        $this->getQueryDateTo($query)->getEpoch(),
        $query->getParameter('display'));

    $day_view = id(new PHUICalendarDayView(
      $this->getQueryDateFrom($query)->getEpoch(),
      $this->getQueryDateTo($query)->getEpoch(),
      $start_year,
      $start_month,
      $start_day))
      ->setQuery($query->getQueryKey());

    $day_view->setUser($viewer);

    $phids = mpull($statuses, 'getUserPHID');

    foreach ($statuses as $status) {
      if ($status->getIsCancelled()) {
        continue;
      }

      $viewer_is_invited = $status->getIsUserInvited($viewer->getPHID());

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $status,
        PhabricatorPolicyCapability::CAN_EDIT);

      $event = new AphrontCalendarEventView();
      $event->setCanEdit($can_edit);
      $event->setEventID($status->getID());
      $event->setEpochRange($status->getDateFrom(), $status->getDateTo());
      $event->setIsAllDay($status->getIsAllDay());
      $event->setIcon($status->getIcon());
      $event->setViewerIsInvited($viewer_is_invited);

      $event->setName($status->getName());
      $event->setURI($status->getURI());
      $day_view->addEvent($event);
    }

    $day_view->setBrowseURI(
      $this->getURI('query/'.$query->getQueryKey().'/'));

    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($day_view);

    return $result;
  }

  private function getDisplayYearAndMonthAndDay(
    $range_start,
    $range_end,
    $display) {

    $viewer = $this->requireViewer();
    $epoch = null;

    if ($this->calendarYear && $this->calendarMonth) {
      $start_year = $this->calendarYear;
      $start_month = $this->calendarMonth;
      $start_day = $this->calendarDay ? $this->calendarDay : 1;
    } else {
      if ($range_start) {
        $epoch = $range_start;
      } else if ($range_end) {
        $epoch = $range_end;
      } else {
        $epoch = time();
      }
      if ($display == 'month') {
        $day = 1;
      } else {
        $day = phabricator_format_local_time($epoch, $viewer, 'd');
      }
      $start_year = phabricator_format_local_time($epoch, $viewer, 'Y');
      $start_month = phabricator_format_local_time($epoch, $viewer, 'm');
      $start_day = $day;
    }
    return array($start_year, $start_month, $start_day);
  }

  public function getPageSize(PhabricatorSavedQuery $saved) {
    if ($this->isMonthView($saved) || $this->isDayView($saved)) {
      return $saved->getParameter('limit', 1000);
    } else {
      return $saved->getParameter('limit', 100);
    }
  }

  private function getQueryDateFrom(PhabricatorSavedQuery $saved) {
    return $this->getQueryDate($saved, 'rangeStart');
  }

  private function getQueryDateTo(PhabricatorSavedQuery $saved) {
    return $this->getQueryDate($saved, 'rangeEnd');
  }

  private function getQueryDate(PhabricatorSavedQuery $saved, $key) {
    $viewer = $this->requireViewer();

    $wild = $saved->getParameter($key);
    return $this->getSafeDate($wild);
  }

  private function getSafeDate($value) {
    $viewer = $this->requireViewer();
    if ($value) {
      // ideally this would be consistent and always pass in the same type
      if ($value instanceof AphrontFormDateControlValue) {
        return $value;
      } else {
        $value = AphrontFormDateControlValue::newFromWild($viewer, $value);
      }
    } else {
      $value = AphrontFormDateControlValue::newFromEpoch(
        $viewer,
        PhabricatorTime::getTodayMidnightDateTime($viewer)->format('U'));
      $value->setEnabled(false);
    }

    $value->setOptional(true);

    return $value;
  }

  private function isMonthView(PhabricatorSavedQuery $query) {
    if ($this->isDayView($query)) {
      return false;
    }
    if ($query->getParameter('display') == 'month') {
      return true;
    }
  }

  private function isDayView(PhabricatorSavedQuery $query) {
    if ($query->getParameter('display') == 'day') {
      return true;
    }
    if ($this->calendarDay) {
      return true;
    }

    return false;
  }

  private function getEventDateLabel($event) {
    $viewer = $this->requireViewer();

    $from_datetime = PhabricatorTime::getDateTimeFromEpoch(
      $event->getDateFrom(),
      $viewer);
    $to_datetime = PhabricatorTime::getDateTimeFromEpoch(
      $event->getDateTo(),
      $viewer);

    $from_date_formatted = $from_datetime->format('Y m d');
    $to_date_formatted = $to_datetime->format('Y m d');

    if ($event->getIsAllDay()) {
      if ($from_date_formatted == $to_date_formatted) {
        return pht(
          '%s, All Day',
          phabricator_date($event->getDateFrom(), $viewer));
      } else {
        return pht(
          '%s - %s, All Day',
          phabricator_date($event->getDateFrom(), $viewer),
          phabricator_date($event->getDateTo(), $viewer));
      }
    } else if ($from_date_formatted == $to_date_formatted) {
      return pht(
        '%s - %s',
        phabricator_datetime($event->getDateFrom(), $viewer),
        phabricator_time($event->getDateTo(), $viewer));
    } else {
      return pht(
        '%s - %s',
        phabricator_datetime($event->getDateFrom(), $viewer),
        phabricator_datetime($event->getDateTo(), $viewer));
    }
  }
}

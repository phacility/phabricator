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
    $viewer = $this->requireViewer();

    return id(new PhabricatorCalendarEventQuery())
      ->needRSVPs(array($viewer->getPHID()));
  }

  protected function shouldShowOrderField() {
    return false;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Hosts'))
        ->setKey('hostPHIDs')
        ->setAliases(array('host', 'hostPHID', 'hosts'))
        ->setDatasource(new PhabricatorPeopleUserFunctionDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Invited'))
        ->setKey('invitedPHIDs')
        ->setDatasource(new PhabricatorCalendarInviteeDatasource()),
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
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Import Sources'))
        ->setKey('importSourcePHIDs')
        ->setAliases(array('importSourcePHID')),
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

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = parent::buildQueryFromSavedQuery($saved);

    // If this is an export query for generating an ".ics" file, don't
    // build ghost events.
    if ($saved->getParameter('export')) {
      $query->setGenerateGhosts(false);
    }

    return $query;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    $viewer = $this->requireViewer();

    if ($map['hostPHIDs']) {
      $query->withHostPHIDs($map['hostPHIDs']);
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

    if ($map['importSourcePHIDs']) {
      $query->withImportSourcePHIDs($map['importSourcePHIDs']);
    }

    if (!$map['ids'] && !$map['phids']) {
      $query
        ->withIsStub(false)
        ->setGenerateGhosts(true);
    }

    return $query;
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

      $start_of_week = $viewer->getUserSetting(
        PhabricatorWeekStartDaySetting::SETTINGKEY);

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
      $now = PhabricatorTime::getNow();
      if ($min_range) {
        $min_range = max($now, $min_range);
      } else {
        $min_range = $now;
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

  protected function renderResultList(
    array $events,
    PhabricatorSavedQuery $query,
    array $handles) {

    if ($this->isMonthView($query)) {
      $result = $this->buildCalendarMonthView($events, $query);
    } else if ($this->isDayView($query)) {
      $result = $this->buildCalendarDayView($events, $query);
    } else {
      $result = $this->buildCalendarListView($events, $query);
    }

    return $result;
  }

  private function buildCalendarListView(
    array $events,
    PhabricatorSavedQuery $query) {

    assert_instances_of($events, 'PhabricatorCalendarEvent');
    $viewer = $this->requireViewer();
    $list = new PHUIObjectItemListView();

    foreach ($events as $event) {
      if ($event->getIsGhostEvent()) {
        $monogram = $event->getParentEvent()->getMonogram();
        $index = $event->getSequenceIndex();
        $monogram = "{$monogram}/{$index}";
      } else {
        $monogram = $event->getMonogram();
      }

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($event)
        ->setObjectName($monogram)
        ->setHeader($event->getName())
        ->setHref($event->getURI());

      $item->addAttribute($event->renderEventDate($viewer, false));

      if ($event->getIsCancelled()) {
        $item->setDisabled(true);
      }

      $status_icon = $event->getDisplayIcon($viewer);
      $status_color = $event->getDisplayIconColor($viewer);
      $status_label = $event->getDisplayIconLabel($viewer);

      $item->setStatusIcon("{$status_icon} {$status_color}", $status_label);

      $host = pht(
        'Hosted by %s',
        $viewer->renderHandle($event->getHostPHID()));
      $item->addByline($host);

      $list->addItem($item);
    }

    return $this->newResultView()
      ->setObjectList($list)
      ->setNoDataString(pht('No events found.'));
  }

  private function buildCalendarMonthView(
    array $events,
    PhabricatorSavedQuery $query) {
    assert_instances_of($events, 'PhabricatorCalendarEvent');

    $viewer = $this->requireViewer();
    $now = PhabricatorTime::getNow();

    list($start_year, $start_month) =
      $this->getDisplayYearAndMonthAndDay(
        $this->getQueryDateFrom($query)->getEpoch(),
        $this->getQueryDateTo($query)->getEpoch(),
        $query->getParameter('display'));

    $now_year = phabricator_format_local_time($now, $viewer, 'Y');
    $now_month = phabricator_format_local_time($now, $viewer, 'm');
    $now_day = phabricator_format_local_time($now, $viewer, 'j');

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

    $viewer_phid = $viewer->getPHID();
    foreach ($events as $event) {
      $epoch_min = $event->getStartDateTimeEpoch();
      $epoch_max = $event->getEndDateTimeEpoch();

      $is_invited = $event->isRSVPInvited($viewer_phid);
      $is_attending = $event->getIsUserAttending($viewer_phid);

      $event_view = id(new AphrontCalendarEventView())
        ->setHostPHID($event->getHostPHID())
        ->setEpochRange($epoch_min, $epoch_max)
        ->setIsCancelled($event->getIsCancelled())
        ->setName($event->getName())
        ->setURI($event->getURI())
        ->setIsAllDay($event->getIsAllDay())
        ->setIcon($event->getDisplayIcon($viewer))
        ->setViewerIsInvited($is_invited || $is_attending)
        ->setDatetimeSummary($event->renderEventDate($viewer, true))
        ->setIconColor($event->getDisplayIconColor($viewer));

      $month_view->addEvent($event_view);
    }

    $month_view->setBrowseURI(
      $this->getURI('query/'.$query->getQueryKey().'/'));

    $from = $this->getQueryDateFrom($query)->getDateTime();

    $crumbs = array();
    $crumbs[] = id(new PHUICrumbView())
      ->setName($from->format('F Y'));

    $header = id(new PHUIHeaderView())
      ->setProfileHeader(true)
      ->setHeader($from->format('F Y'));

    return $this->newResultView($month_view)
      ->setCrumbs($crumbs)
      ->setHeader($header);
  }

  private function buildCalendarDayView(
    array $events,
    PhabricatorSavedQuery $query) {

    $viewer = $this->requireViewer();

    list($start_year, $start_month, $start_day) =
      $this->getDisplayYearAndMonthAndDay(
        $this->getQueryDateFrom($query)->getEpoch(),
        $this->getQueryDateTo($query)->getEpoch(),
        $query->getParameter('display'));

    $day_view = id(new PHUICalendarDayView(
      $this->getQueryDateFrom($query),
      $this->getQueryDateTo($query),
      $start_year,
      $start_month,
      $start_day))
      ->setQuery($query->getQueryKey());

    $day_view->setUser($viewer);

    $phids = mpull($events, 'getHostPHID');

    foreach ($events as $event) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $event,
        PhabricatorPolicyCapability::CAN_EDIT);

      $epoch_min = $event->getStartDateTimeEpoch();
      $epoch_max = $event->getEndDateTimeEpoch();

      $status_icon = $event->getDisplayIcon($viewer);
      $status_color = $event->getDisplayIconColor($viewer);

      $event_view = id(new AphrontCalendarEventView())
        ->setCanEdit($can_edit)
        ->setEventID($event->getID())
        ->setEpochRange($epoch_min, $epoch_max)
        ->setIsAllDay($event->getIsAllDay())
        ->setIcon($status_icon)
        ->setIconColor($status_color)
        ->setName($event->getName())
        ->setURI($event->getURI())
        ->setDatetimeSummary($event->renderEventDate($viewer, true))
        ->setIsCancelled($event->getIsCancelled());

      $day_view->addEvent($event_view);
    }

    $browse_uri = $this->getURI('query/'.$query->getQueryKey().'/');
    $day_view->setBrowseURI($browse_uri);

    $from = $this->getQueryDateFrom($query)->getDateTime();
    $month_uri = $browse_uri.$from->format('Y/m/');

    $crumbs = array(
      id(new PHUICrumbView())
        ->setName($from->format('F Y'))
        ->setHref($month_uri),
      id(new PHUICrumbView())
        ->setName($from->format('D jS')),
    );

    $header = id(new PHUIHeaderView())
      ->setProfileHeader(true)
      ->setHeader($from->format('D, F jS'));

    return $this->newResultView($day_view)
      ->setCrumbs($crumbs)
      ->setHeader($header);
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
    if ($this->calendarYear && $this->calendarMonth) {
      $viewer = $this->requireViewer();

      $start_year = $this->calendarYear;
      $start_month = $this->calendarMonth;
      $start_day = $this->calendarDay ? $this->calendarDay : 1;

      return AphrontFormDateControlValue::newFromDictionary(
        $viewer,
        array(
          'd' => "{$start_year}-{$start_month}-{$start_day}",
        ));
    }

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

  public function newUseResultsActions(PhabricatorSavedQuery $saved) {
    $viewer = $this->requireViewer();
    $can_export = $viewer->isLoggedIn();

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-download')
        ->setName(pht('Export Query as .ics'))
        ->setDisabled(!$can_export)
        ->setHref('/calendar/export/edit/?queryKey='.$saved->getQueryKey()),
    );
  }


  private function newResultView($content = null) {
    // If we aren't rendering a dashboard panel, activate global drag-and-drop
    // so you can import ".ics" files by dropping them directly onto the
    // calendar.
    if (!$this->isPanelContext()) {
      $drop_upload = id(new PhabricatorGlobalUploadTargetView())
        ->setViewer($this->requireViewer())
        ->setHintText("\xE2\x87\xAA ".pht('Drop .ics Files to Import'))
        ->setSubmitURI('/calendar/import/drop/')
        ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE);

      $content = array(
        $drop_upload,
        $content,
      );
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setContent($content);
  }

}

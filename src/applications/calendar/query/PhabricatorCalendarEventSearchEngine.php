<?php

final class PhabricatorCalendarEventSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Calendar Events');
  }

  public function getApplicationClassName() {
    return 'PhabricatorCalendarApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'rangeStart',
      $this->readDateFromRequest($request, 'rangeStart'));

    $saved->setParameter(
      'rangeEnd',
      $this->readDateFromRequest($request, 'rangeEnd'));

    $saved->setParameter(
      'upcoming',
      $this->readBoolFromRequest($request, 'upcoming'));

    $saved->setParameter(
      'invitedPHIDs',
      $this->readUsersFromRequest($request, 'invited'));

    $saved->setParameter(
      'creatorPHIDs',
      $this->readUsersFromRequest($request, 'creators'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorCalendarEventQuery());

    $min_range = null;
    $max_range = null;

    if ($saved->getParameter('rangeStart')) {
      $min_range = $saved->getParameter('rangeStart');
    }

    if ($saved->getParameter('rangeEnd')) {
      $max_range = $saved->getParameter('rangeEnd');
    }

    if ($saved->getParameter('upcoming')) {
      if ($min_range) {
        $min_range = max(time(), $min_range);
      } else {
        $min_range = time();
      }
    }

    if ($min_range || $max_range) {
      $query->withDateRange($min_range, $max_range);
    }

    $invited_phids = $saved->getParameter('invitedPHIDs');
    if ($invited_phids) {
      $query->withInvitedPHIDs($invited_phids);
    }

    $creator_phids = $saved->getParameter('creatorPHIDs');
    if ($creator_phids) {
      $query->withCreatorPHIDs($creator_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $range_start = $saved->getParameter('rangeStart');
    $range_end = $saved->getParameter('rangeEnd');
    $upcoming = $saved->getParameter('upcoming');

    $invited_phids = $saved->getParameter('invitedPHIDs', array());
    $creator_phids = $saved->getParameter('creatorPHIDs', array());

    $all_phids = array_merge(
      $invited_phids,
      $creator_phids);

    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    $invited_handles = array_select_keys($handles, $invited_phids);
    $creator_handles = array_select_keys($handles, $creator_phids);

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('creators')
          ->setLabel(pht('Created By'))
          ->setValue($creator_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('invited')
          ->setLabel(pht('Invited'))
          ->setValue($invited_handles))
      ->appendChild(
        id(new AphrontFormDateControl())
          ->setLabel(pht('Occurs After'))
          ->setUser($this->requireViewer())
          ->setName('rangeStart')
          ->setAllowNull(true)
          ->setValue($range_start))
      ->appendChild(
        id(new AphrontFormDateControl())
          ->setLabel(pht('Occurs Before'))
          ->setUser($this->requireViewer())
          ->setName('rangeEnd')
          ->setAllowNull(true)
          ->setValue($range_end))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'upcoming',
            1,
            pht('Show only upcoming events.'),
            $upcoming));
  }

  protected function getURI($path) {
    return '/calendar/event/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'upcoming' => pht('Upcoming Events'),
      'all'      => pht('All Events'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'upcoming':
        return $query->setParameter('upcoming', true);
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
    assert_instances_of($events, 'PhabricatorCalendarEvent');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    foreach ($events as $event) {
      if ($event->getUserPHID() == $viewer->getPHID()) {
        $href = $this->getApplicationURI('/event/edit/'.$event->getID().'/');
      } else {
        $from  = $event->getDateFrom();
        $month = phabricator_format_local_time($from, $viewer, 'm');
        $year  = phabricator_format_local_time($from, $viewer, 'Y');
        $uri   = new PhutilURI($this->getApplicationURI());
        $uri->setQueryParams(
          array(
            'month' => $month,
            'year'  => $year,
          ));
        $href = (string) $uri;
      }
      $from = phabricator_datetime($event->getDateFrom(), $viewer);
      $to   = phabricator_datetime($event->getDateTo(), $viewer);
      $creator_handle = $handles[$event->getUserPHID()];

      $color = ($event->getStatus() == PhabricatorCalendarEvent::STATUS_AWAY)
        ? 'red'
        : 'yellow';

      $item = id(new PHUIObjectItemView())
        ->setHeader($event->getTerseSummary($viewer))
        ->setHref($href)
        ->setBarColor($color)
        ->addByline(pht('Creator: %s', $creator_handle->renderLink()))
        ->addAttribute(pht('From %s to %s', $from, $to))
        ->addAttribute(id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(64)
          ->truncateString($event->getDescription()));

      $list->addItem($item);
    }

    return $list;
  }

}

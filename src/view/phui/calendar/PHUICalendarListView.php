<?php

final class PHUICalendarListView extends AphrontTagView {

  private $events = array();
  private $blankState;

  public function addEvent(AphrontCalendarEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function showBlankState($state) {
    $this->blankState = $state;
    return $this;
  }

  public function getTagName() {
    return 'div';
  }

  public function getTagAttributes() {
    require_celerity_resource('phui-calendar-css');
    require_celerity_resource('phui-calendar-list-css');
    return array('class' => 'phui-calendar-day-list');
  }

  protected function getTagContent() {
    if (!$this->blankState && empty($this->events)) {
      return '';
    }

    $events = msort($this->events, 'getEpochStart');

    // All Day Event (well, 23 hours, 59 minutes worth)
    $timespan = ((3600 * 24) - 60);

    $singletons = array();
    $allday = false;
    foreach ($events as $event) {
      $color = $event->getColor();

      $length = ($event->getEpochEnd() - $event->getEpochStart());
      if ($length >= $timespan) {
        $timelabel = pht('All Day');
      } else {
        $timelabel = phabricator_time(
          $event->getEpochStart(),
          $this->getUser());
      }

      $dot = phutil_tag(
        'span',
        array(
          'class' => 'phui-calendar-list-dot'),
        '');
      $title = phutil_tag(
        'span',
        array(
          'class' => 'phui-calendar-list-title'),
        $this->renderEventLink($event, $allday));
      $time = phutil_tag(
        'span',
        array(
          'class' => 'phui-calendar-list-time'),
        $timelabel);

      $singletons[] = phutil_tag(
        'li',
        array(
          'class' => 'phui-calendar-list-item phui-calendar-'.$color
          ),
        array(
          $dot,
          $title,
          $time));
    }

    if (empty($singletons)) {
      $singletons[] = phutil_tag(
        'li',
        array(
          'class' => 'phui-calendar-list-item-empty'
          ),
        pht('Clear sailing ahead.'));
    }

    $list = phutil_tag(
      'ul',
      array(
        'class' => 'phui-calendar-list'
      ),
      $singletons);

    return $list;
  }

  private function renderEventLink($event) {

    Javelin::initBehavior('phabricator-tooltips');

    // Multiple Days
    $timespan = ((3600 * 24) + 60);
    $length = ($event->getEpochEnd() - $event->getEpochStart());
    if ($length >= $timespan) {
      $tip = pht('%s, Until: %s', $event->getName(),
        phabricator_date($event->getEpochEnd(), $this->getUser()));
    } else {
      $tip = pht('%s, Until: %s', $event->getName(),
        phabricator_time($event->getEpochEnd(), $this->getUser()));
    }

    $description = $event->getDescription();
    if (strlen($description) == 0) {
      $description = pht('(%s)', $event->getName());
    }

    $anchor = javelin_tag(
      'a',
      array(
        'sigil' => 'has-tooltip',
        'class' => 'phui-calendar-item-link',
        'href' => '/calendar/event/view/'.$event->getEventID().'/',
        'meta'  => array(
          'tip'  => $tip,
          'size' => 200,
        ),
      ),
      $description);

    return $anchor;
  }
}

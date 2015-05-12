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

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-calendar-css');
    require_celerity_resource('phui-calendar-list-css');
    return array('class' => 'phui-calendar-event-list');
  }

  protected function getTagContent() {
    if (!$this->blankState && empty($this->events)) {
      return '';
    }

    $singletons = array();
    $allday = false;
    foreach ($this->events as $event) {
      $color = $event->getColor();
      $start_epoch = $event->getEpochStart();

      if ($event->getIsAllDay()) {
        $timelabel = pht('All Day');
        $dot = null;
      } else {
        $timelabel = phabricator_time(
          $event->getEpochStart(),
          $this->getUser());

        $dot = phutil_tag(
          'span',
          array(
            'class' => 'phui-calendar-list-dot',
          ),
          '');
      }

      $title = phutil_tag(
        'span',
        array(
          'class' => 'phui-calendar-list-title',
        ),
        $this->renderEventLink($event, $allday));
      $time = phutil_tag(
        'span',
        array(
          'class' => 'phui-calendar-list-time',
        ),
        $timelabel);

      $class = 'phui-calendar-list-item phui-calendar-'.$color;
      if ($event->getIsAllDay()) {
        $class = $class.' all-day';
      }

      $singletons[] = phutil_tag(
        'li',
        array(
          'class' => $class,
          ),
        array(
          $dot,
          $title,
          $time,
        ));
    }

    if (empty($singletons)) {
      $singletons[] = phutil_tag(
        'li',
        array(
          'class' => 'phui-calendar-list-item-empty',
        ),
        pht('Clear sailing ahead.'));
    }

    $list = phutil_tag(
      'ul',
      array(
        'class' => 'phui-calendar-list',
      ),
      $singletons);

    return $list;
  }

  private function renderEventLink($event) {

    Javelin::initBehavior('phabricator-tooltips');

    if ($event->getMultiDay()) {
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

    $class = 'phui-calendar-item-link';

    $anchor = javelin_tag(
      'a',
      array(
        'sigil' => 'has-tooltip',
        'class' => $class,
        'href' => '/E'.$event->getEventID(),
        'meta'  => array(
          'tip'  => $tip,
          'size' => 200,
        ),
      ),
      $event->getName());

    return $anchor;
  }
}

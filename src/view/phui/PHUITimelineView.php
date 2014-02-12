<?php

final class PHUITimelineView extends AphrontView {

  private $events = array();
  private $id;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function addEvent(PHUITimelineEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function render() {
    require_celerity_resource('phui-timeline-view-css');

    $spacer = self::renderSpacer();

    $events = array();
    foreach ($this->events as $event) {
      $events[] = $spacer;
      $events[] = $event;
    }
    $events[] = $spacer;

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-view',
        'id' => $this->id,
      ),
      $events);
  }

  public static function renderSpacer() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-event-view '.
                   'phui-timeline-spacer',
      ),
      '');
  }
}

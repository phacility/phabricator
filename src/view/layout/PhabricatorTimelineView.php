<?php

final class PhabricatorTimelineView extends AphrontView {

  private $events = array();

  public function addEvent(PhabricatorTimelineEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-timeline-view-css');

    $spacer = phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-timeline-event-view '.
                   'phabricator-timeline-spacer',
      ),
      '');

    $events = array();
    foreach ($this->events as $event) {
      $events[] = $spacer;
      $events[] = $this->renderSingleView($event);
    }
    $events[] = $spacer;

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-timeline-view',
      ),
      implode('', $events));
  }

}

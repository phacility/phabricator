<?php

final class PhabricatorTimelineView extends AphrontView {

  private $events = array();

  public function addEvent(PhabricatorTimelineEventView $event) {
    $this->events[] = $event;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-timeline-view-css');

    $events = array();
    foreach ($this->events as $event) {
      $events[] = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-timeline-event-view '.
                     'phabricator-timeline-spacer',
        ),
        '');
      $events[] = $this->renderSingleView($event);
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-timeline-view',
      ),
      implode('', $events));
  }

}

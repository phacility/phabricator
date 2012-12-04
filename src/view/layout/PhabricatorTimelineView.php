<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

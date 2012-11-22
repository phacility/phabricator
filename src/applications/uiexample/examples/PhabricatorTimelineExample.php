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

final class PhabricatorTimelineExample extends PhabricatorUIExample {

  public function getName() {
    return 'Timeline View';
  }

  public function getDescription() {
    return 'Use <tt>PhabricatorTimelineView</tt> to comments and transactions.';
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $user->getPHID(),
      $user);

    $events = array();

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('A major event.')
      ->appendChild('This is a major timeline event.');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('A minor event.');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->appendChild('A major event with no title.');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Another minor event.');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle);

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Major Red Event')
      ->appendChild('This event is red!')
      ->addClass('phabricator-timeline-red');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Minor Red Event')
      ->addClass('phabricator-timeline-red');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Minor Not-Red Event');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Minor Red Event')
      ->addClass('phabricator-timeline-red');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Minor Not-Red Event');

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Unstyled event')
      ->appendChild('This event disables standard title and content styling.')
      ->setDisableStandardTitleStyle(true)
      ->setDisableStandardContentStyle(true);

    $events[] = id(new PhabricatorTimelineEventView())
      ->setUserHandle($handle)
      ->setTitle('Major Green Event')
      ->appendChild('This event is green!')
      ->addClass('phabricator-timeline-green');

    $timeline = id(new PhabricatorTimelineView());
    foreach ($events as $event) {
      $timeline->addEvent($event);
    }

    return $timeline;
  }
}

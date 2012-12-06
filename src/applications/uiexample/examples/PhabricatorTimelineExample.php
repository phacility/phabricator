<?php

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

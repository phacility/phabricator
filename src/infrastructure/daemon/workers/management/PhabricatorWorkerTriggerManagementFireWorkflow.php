<?php

final class PhabricatorWorkerTriggerManagementFireWorkflow
  extends PhabricatorWorkerTriggerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('fire')
      ->setExamples('**fire** --id __id__')
      ->setSynopsis(
        pht(
          'Activates selected triggers, firing them immediately.'))
      ->setArguments(
        array_merge(
          array(
            array(
              'name' => 'now',
              'param' => 'time',
              'help' => pht(
                'Fire the trigger as though the current time is a given '.
                'time. This allows you to test how a trigger would behave '.
                'if activated in the past or future. Defaults to the actual '.
                'current time.'),
            ),
            array(
              'name' => 'last',
              'param' => 'time',
              'help' => pht(
                'Fire the trigger as though the last event occurred at a '.
                'given time. Defaults to the actual last event time.'),
            ),
            array(
              'name' => 'next',
              'param' => 'time',
              'help' => pht(
                'Fire the trigger as though the next event was scheduled '.
                'at a given time. Defaults to the actual time when the '.
                'event is next scheduled to fire.'),
            ),
          ),
          $this->getTriggerSelectionArguments()));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();
    $triggers = $this->loadTriggers($args);

    $now = $args->getArg('now');
    $now = $this->parseTimeArgument($now);
    if (!$now) {
      $now = PhabricatorTime::getNow();
    }

    $time_guard = PhabricatorTime::pushTime($now, date_default_timezone_get());

    $console->writeOut(
      "%s\n",
      pht(
        'Set current time to %s.',
        phabricator_datetime(PhabricatorTime::getNow(), $viewer)));

    $last_time = $this->parseTimeArgument($args->getArg('last'));
    $next_time = $this->parseTimeArgument($args->getArg('next'));

    PhabricatorWorker::setRunAllTasksInProcess(true);

    foreach ($triggers as $trigger) {
      $console->writeOut(
        "%s\n",
        pht('Executing trigger %s.', $this->describeTrigger($trigger)));

      $event = $trigger->getEvent();
      if ($event) {
        if (!$last_time) {
          $last_time = $event->getLastEventEpoch();
        }
        if (!$next_time) {
          $next_time = $event->getNextEventEpoch();
        }
      }

      if (!$next_time) {
        $console->writeOut(
          "%s\n",
          pht(
            'Trigger is not scheduled to execute. Use --next to simluate '.
            'a scheduled event.'));
        continue;
      } else {
        $console->writeOut(
          "%s\n",
          pht(
            'Executing event as though it was scheduled to execute at %s.',
            phabricator_datetime($next_time, $viewer)));
      }

      if (!$last_time) {
        $console->writeOut(
          "%s\n",
          pht(
            'Executing event as though it never previously executed.'));
      } else {
        $console->writeOut(
          "%s\n",
          pht(
            'Executing event as though it previously executed at %s.',
            phabricator_datetime($last_time, $viewer)));
      }

      $trigger->executeTrigger($last_time, $next_time);

      $reschedule_time = $trigger->getNextEventEpoch(
        $next_time,
        $is_reschedule = true);

      if (!$reschedule_time) {
        $console->writeOut(
          "%s\n",
          pht(
            'After executing under these conditions, this event would never '.
            'execute again.'));
      } else {
        $console->writeOut(
          "%s\n",
          pht(
            'After executing under these conditions, this event would '.
            'next execute at %s.',
            phabricator_datetime($reschedule_time, $viewer)));
      }
    }

    return 0;
  }

}

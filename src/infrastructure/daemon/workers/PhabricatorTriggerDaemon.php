<?php

/**
 * Schedule and execute event triggers, which run code at specific times.
 *
 * Also performs garbage collection of old logs, caches, etc.
 *
 * @task garbage Garbage Collection
 */
final class PhabricatorTriggerDaemon
  extends PhabricatorDaemon {

  const COUNTER_VERSION = 'trigger.version';
  const COUNTER_CURSOR = 'trigger.cursor';

  private $garbageCollectors;
  private $nextCollection;

  private $anyNuanceData;
  private $nuanceSources;
  private $nuanceCursors;

  private $calendarEngine;

  protected function run() {

    // The trigger daemon is a low-level infrastructure daemon which schedules
    // and executes chronological events. Examples include a subscription which
    // generates a bill on the 12th of every month, or a reminder email 15
    // minutes before a meeting.

    // Only one trigger daemon can run at a time, and very little work should
    // happen in the daemon process. In general, triggered events should
    // just schedule a task into the normal daemon worker queue and then
    // return. This allows the real work to take longer to execute without
    // disrupting other triggers.

    // The trigger mechanism guarantees that events will execute exactly once,
    // but does not guarantee that they will execute at precisely the specified
    // time. Under normal circumstances, they should execute within a minute or
    // so of the desired time, so this mechanism can be used for things like
    // meeting reminders.

    // If the trigger queue backs up (for example, because it is overwhelmed by
    // trigger updates, doesn't run for a while, or a trigger action is written
    // inefficiently) or the daemon queue backs up (usually for similar
    // reasons), events may execute an arbitrarily long time after they were
    // scheduled to execute. In some cases (like billing a subscription) this
    // may be desirable; in other cases (like sending a meeting reminder) the
    // action may want to check the current time and see if the event is still
    // relevant.

    // The trigger daemon works in two phases:
    //
    //   1. A scheduling phase processes recently updated triggers and
    //      schedules them for future execution. For example, this phase would
    //      see that a meeting trigger had been changed recently, determine
    //      when the reminder for it should execute, and then schedule the
    //      action to execute at that future date.
    //   2. An execution phase runs the actions for any scheduled events which
    //      are due to execute.
    //
    // The major goal of this design is to deliver on the guarantee that events
    // will execute exactly once. It prevents race conditions in scheduling
    // and execution by ensuring there is only one writer for either of these
    // phases. Without this separation of responsibilities, web processes
    // trying to reschedule events after an update could race with other web
    // processes or the daemon.

    // We want to start the first GC cycle right away, not wait 4 hours.
    $this->nextCollection = PhabricatorTime::getNow();

    do {
      PhabricatorCaches::destroyRequestCache();

      $lock = PhabricatorGlobalLock::newLock('trigger');

      try {
        $lock->lock(5);
      } catch (PhutilLockException $ex) {
        throw new PhutilProxyException(
          pht(
            'Another process is holding the trigger lock. Usually, this '.
            'means another copy of the trigger daemon is running elsewhere. '.
            'Multiple processes are not permitted to update triggers '.
            'simultaneously.'),
          $ex);
      }

      // Run the scheduling phase. This finds updated triggers which we have
      // not scheduled yet and schedules them.
      $last_version = $this->loadCurrentCursor();
      $head_version = $this->loadCurrentVersion();

      // The cursor points at the next record to process, so we can only skip
      // this step if we're ahead of the version number.
      if ($last_version <= $head_version) {
        $this->scheduleTriggers($last_version);
      }

      // Run the execution phase. This finds events which are due to execute
      // and runs them.
      $this->executeTriggers();

      $lock->unlock();

      $sleep_duration = $this->getSleepDuration();
      $sleep_duration = $this->runNuanceImportCursors($sleep_duration);
      $sleep_duration = $this->runGarbageCollection($sleep_duration);
      $sleep_duration = $this->runCalendarNotifier($sleep_duration);

      if ($this->shouldHibernate($sleep_duration)) {
        break;
      }

      $this->sleep($sleep_duration);
    } while (!$this->shouldExit());
  }


  /**
   * Process all of the triggers which have been updated since the last time
   * the daemon ran, scheduling them into the event table.
   *
   * @param int Cursor for the next version update to process.
   * @return void
   */
  private function scheduleTriggers($cursor) {
    $limit = 100;

    $query = id(new PhabricatorWorkerTriggerQuery())
      ->setViewer($this->getViewer())
      ->withVersionBetween($cursor, null)
      ->setOrder(PhabricatorWorkerTriggerQuery::ORDER_VERSION)
      ->needEvents(true)
      ->setLimit($limit);
    while (true) {
      $triggers = $query->execute();

      foreach ($triggers as $trigger) {
        $event = $trigger->getEvent();
        if ($event) {
          $last_epoch = $event->getLastEventEpoch();
        } else {
          $last_epoch = null;
        }

        $next_epoch = $trigger->getNextEventEpoch(
          $last_epoch,
          $is_reschedule = false);

        $new_event = PhabricatorWorkerTriggerEvent::initializeNewEvent($trigger)
          ->setLastEventEpoch($last_epoch)
          ->setNextEventEpoch($next_epoch);

        $new_event->openTransaction();
          if ($event) {
            $event->delete();
          }

          // Always save the new event. Note that we save it even if the next
          // epoch is `null`, indicating that it will never fire, because we
          // would lose the last epoch information if we delete it.
          //
          // In particular, some events may want to execute exactly once.
          // Retaining the last epoch allows them to do this, even if the
          // trigger is updated.
          $new_event->save();

          // Move the cursor forward to make sure we don't reprocess this
          // trigger until it is updated again.
          $this->updateCursor($trigger->getTriggerVersion() + 1);
        $new_event->saveTransaction();
      }

      // If we saw fewer than a full page of updated triggers, we're caught
      // up, so we can move on to the execution phase.
      if (count($triggers) < $limit) {
        break;
      }

      // Otherwise, skip past the stuff we just processed and grab another
      // page of updated triggers.
      $min = last($triggers)->getTriggerVersion() + 1;
      $query->withVersionBetween($min, null);

      $this->stillWorking();
    }
  }


  /**
   * Run scheduled event triggers which are due for execution.
   *
   * @return void
   */
  private function executeTriggers() {

    // We run only a limited number of triggers before ending the execution
    // phase. If we ran until exhaustion, we could end up executing very
    // out-of-date triggers if there was a long backlog: trigger changes
    // during this phase are not reflected in the event table until we run
    // another scheduling phase.

    // If we exit this phase with triggers still ready to execute we'll
    // jump back into the scheduling phase immediately, so this just makes
    // sure we don't spend an unreasonably long amount of time without
    // processing trigger updates and doing rescheduling.

    $limit = 100;
    $now = PhabricatorTime::getNow();

    $triggers = id(new PhabricatorWorkerTriggerQuery())
      ->setViewer($this->getViewer())
      ->setOrder(PhabricatorWorkerTriggerQuery::ORDER_EXECUTION)
      ->withNextEventBetween(null, $now)
      ->needEvents(true)
      ->setLimit($limit)
      ->execute();
    foreach ($triggers as $trigger) {
      $event = $trigger->getEvent();

      // Execute the trigger action.
      $trigger->executeTrigger(
        $event->getLastEventEpoch(),
        $event->getNextEventEpoch());

      // Now that we've executed the trigger, the current trigger epoch is
      // going to become the last epoch.
      $last_epoch = $event->getNextEventEpoch();

      // If this is a recurring trigger, give it an opportunity to reschedule.
      $reschedule_epoch = $trigger->getNextEventEpoch(
        $last_epoch,
        $is_reschedule = true);

      // Don't reschedule events unless the next occurrence is in the future.
      if (($reschedule_epoch !== null) &&
          ($last_epoch !== null) &&
          ($reschedule_epoch <= $last_epoch)) {
        throw new Exception(
          pht(
            'Trigger is attempting to perform a routine reschedule where '.
            'the next event (at %s) does not occur after the previous event '.
            '(at %s). Routine reschedules must strictly move event triggers '.
            'forward through time to avoid executing a trigger an infinite '.
            'number of times instantaneously.',
            $reschedule_epoch,
            $last_epoch));
      }

      $new_event = PhabricatorWorkerTriggerEvent::initializeNewEvent($trigger)
        ->setLastEventEpoch($last_epoch)
        ->setNextEventEpoch($reschedule_epoch);

      $event->openTransaction();
        // Remove the event we just processed.
        $event->delete();

        // See note in the scheduling phase about this; we save the new event
        // even if the next epoch is `null`.
        $new_event->save();
      $event->saveTransaction();
    }
  }


  /**
   * Get the number of seconds to sleep for before starting the next scheduling
   * phase.
   *
   * If no events are scheduled soon, we'll sleep briefly. Otherwise,
   * we'll sleep until the next scheduled event.
   *
   * @return int Number of seconds to sleep for.
   */
  private function getSleepDuration() {
    $sleep = phutil_units('3 minutes in seconds');

    $next_triggers = id(new PhabricatorWorkerTriggerQuery())
      ->setViewer($this->getViewer())
      ->setOrder(PhabricatorWorkerTriggerQuery::ORDER_EXECUTION)
      ->withNextEventBetween(0, null)
      ->setLimit(1)
      ->needEvents(true)
      ->execute();
    if ($next_triggers) {
      $next_trigger = head($next_triggers);
      $next_epoch = $next_trigger->getEvent()->getNextEventEpoch();
      $until = max(0, $next_epoch - PhabricatorTime::getNow());
      $sleep = min($sleep, $until);
    }

    return $sleep;
  }


/* -(  Counters  )----------------------------------------------------------- */


  private function loadCurrentCursor() {
    return $this->loadCurrentCounter(self::COUNTER_CURSOR);
  }

  private function loadCurrentVersion() {
    return $this->loadCurrentCounter(self::COUNTER_VERSION);
  }

  private function updateCursor($value) {
    LiskDAO::overwriteCounterValue(
      id(new PhabricatorWorkerTrigger())->establishConnection('w'),
      self::COUNTER_CURSOR,
      $value);
  }

  private function loadCurrentCounter($counter_name) {
    return (int)LiskDAO::loadCurrentCounterValue(
      id(new PhabricatorWorkerTrigger())->establishConnection('w'),
      $counter_name);
  }


/* -(  Garbage Collection  )------------------------------------------------- */


  /**
   * Run the garbage collector for up to a specified number of seconds.
   *
   * @param int Number of seconds the GC may run for.
   * @return int Number of seconds remaining in the time budget.
   * @task garbage
   */
  private function runGarbageCollection($duration) {
    $run_until = (PhabricatorTime::getNow() + $duration);

    // NOTE: We always run at least one GC cycle to make sure the GC can make
    // progress even if the trigger queue is busy.
    do {
      $more_garbage = $this->updateGarbageCollection();
      if (!$more_garbage) {
        // If we don't have any more collection work to perform, we're all
        // done.
        break;
      }
    } while (PhabricatorTime::getNow() <= $run_until);

    $remaining = max(0, $run_until - PhabricatorTime::getNow());

    return $remaining;
  }


  /**
   * Update garbage collection, possibly collecting a small amount of garbage.
   *
   * @return bool True if there is more garbage to collect.
   * @task garbage
   */
  private function updateGarbageCollection() {
    // If we're ready to start the next collection cycle, load all the
    // collectors.
    $next = $this->nextCollection;
    if ($next && (PhabricatorTime::getNow() >= $next)) {
      $this->nextCollection = null;

      $all_collectors = PhabricatorGarbageCollector::getAllCollectors();
      $this->garbageCollectors = $all_collectors;
    }

    // If we're in a collection cycle, continue collection.
    if ($this->garbageCollectors) {
      foreach ($this->garbageCollectors as $key => $collector) {
        $more_garbage = $collector->runCollector();
        if (!$more_garbage) {
          unset($this->garbageCollectors[$key]);
        }
        // We only run one collection per call, to prevent triggers from being
        // thrown too far off schedule if there's a lot of garbage to collect.
        break;
      }

      if ($this->garbageCollectors) {
        // If we have more work to do, return true.
        return true;
      }

      // Otherwise, reschedule another cycle in 4 hours.
      $now = PhabricatorTime::getNow();
      $wait = phutil_units('4 hours in seconds');
      $this->nextCollection = $now + $wait;
    }

    return false;
  }


/* -(  Nuance Importers  )--------------------------------------------------- */


  private function runNuanceImportCursors($duration) {
    $run_until = (PhabricatorTime::getNow() + $duration);

    do {
      $more_data = $this->updateNuanceImportCursors();
      if (!$more_data) {
        break;
      }
    } while (PhabricatorTime::getNow() <= $run_until);

    $remaining = max(0, $run_until - PhabricatorTime::getNow());

    return $remaining;
  }


  private function updateNuanceImportCursors() {
    $nuance_app = 'PhabricatorNuanceApplication';
    if (!PhabricatorApplication::isClassInstalled($nuance_app)) {
      return false;
    }

    // If we haven't loaded sources yet, load them first.
    if (!$this->nuanceSources && !$this->nuanceCursors) {
      $this->anyNuanceData = false;

      $sources = id(new NuanceSourceQuery())
        ->setViewer($this->getViewer())
        ->withIsDisabled(false)
        ->withHasImportCursors(true)
        ->execute();
      if (!$sources) {
        return false;
      }

      $this->nuanceSources = array_reverse($sources);
    }

    // If we don't have any cursors, move to the next source and generate its
    // cursors.
    if (!$this->nuanceCursors) {
      $source = array_pop($this->nuanceSources);

      $definition = $source->getDefinition()
        ->setViewer($this->getViewer())
        ->setSource($source);

      $cursors = $definition->getImportCursors();
      $this->nuanceCursors = array_reverse($cursors);
    }

    // Update the next cursor.
    $cursor = array_pop($this->nuanceCursors);
    if ($cursor) {
      $more_data = $cursor->importFromSource();
      if ($more_data) {
        $this->anyNuanceData = true;
      }
    }

    if (!$this->nuanceSources && !$this->nuanceCursors) {
      return $this->anyNuanceData;
    }

    return true;
  }


/* -(  Calendar Notifier  )-------------------------------------------------- */


  private function runCalendarNotifier($duration) {
    $run_until = (PhabricatorTime::getNow() + $duration);

    if (!$this->calendarEngine) {
      $this->calendarEngine = new PhabricatorCalendarNotificationEngine();
    }

    $this->calendarEngine->publishNotifications();

    $remaining = max(0, $run_until - PhabricatorTime::getNow());
    return $remaining;
  }

}

<?php

final class PhabricatorCalendarEventQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $rangeBegin;
  private $rangeEnd;
  private $inviteePHIDs;
  private $hostPHIDs;
  private $isCancelled;
  private $eventsWithNoParent;
  private $instanceSequencePairs;
  private $isStub;

  private $generateGhosts = false;

  public function newResultObject() {
    return new PhabricatorCalendarEvent();
  }

  public function setGenerateGhosts($generate_ghosts) {
    $this->generateGhosts = $generate_ghosts;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDateRange($begin, $end) {
    $this->rangeBegin = $begin;
    $this->rangeEnd = $end;
    return $this;
  }

  public function withInvitedPHIDs(array $phids) {
    $this->inviteePHIDs = $phids;
    return $this;
  }

  public function withHostPHIDs(array $phids) {
    $this->hostPHIDs = $phids;
    return $this;
  }

  public function withIsCancelled($is_cancelled) {
    $this->isCancelled = $is_cancelled;
    return $this;
  }

  public function withIsStub($is_stub) {
    $this->isStub = $is_stub;
    return $this;
  }

  public function withEventsWithNoParent($events_with_no_parent) {
    $this->eventsWithNoParent = $events_with_no_parent;
    return $this;
  }

  public function withInstanceSequencePairs(array $pairs) {
    $this->instanceSequencePairs = $pairs;
    return $this;
  }

  protected function getDefaultOrderVector() {
    return array('start', 'id');
  }

  public function getBuiltinOrders() {
    return array(
      'start' => array(
        'vector' => array('start', 'id'),
        'name' => pht('Event Start'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return array(
      'start' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'dateFrom',
        'reverse' => true,
        'type' => 'int',
        'unique' => false,
      ),
    ) + parent::getOrderableColumns();
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $event = $this->loadCursorObject($cursor);
    return array(
      'start' => $event->getViewerDateFrom(),
      'id' => $event->getID(),
    );
  }

  protected function shouldLimitResults() {
    // When generating ghosts, we can't rely on database ordering because
    // MySQL can't predict the ghost start times. We'll just load all matching
    // events, then generate results from there.
    if ($this->generateGhosts) {
      return false;
    }

    return true;
  }

  protected function loadPage() {
    $events = $this->loadStandardPage($this->newResultObject());

    $viewer = $this->getViewer();
    foreach ($events as $event) {
      $event->applyViewerTimezone($viewer);
    }

    if (!$this->generateGhosts) {
      return $events;
    }

    $raw_limit = $this->getRawResultLimit();

    if (!$raw_limit && !$this->rangeEnd) {
      throw new Exception(
        pht(
          'Event queries which generate ghost events must include either a '.
          'result limit or an end date, because they may otherwise generate '.
          'an infinite number of results. This query has neither.'));
    }

    foreach ($events as $key => $event) {
      $sequence_start = 0;
      $sequence_end = null;
      $end = null;

      $instance_of = $event->getInstanceOfEventPHID();

      if ($instance_of == null && $this->isCancelled !== null) {
        if ($event->getIsCancelled() != $this->isCancelled) {
          unset($events[$key]);
          continue;
        }
      }
    }

    // Pull out all of the parents first. We may discard them as we begin
    // generating ghost events, but we still want to process all of them.
    $parents = array();
    foreach ($events as $key => $event) {
      if ($event->isParentEvent()) {
        $parents[$key] = $event;
      }
    }

    // Now that we've picked out all the parent events, we can immediately
    // discard anything outside of the time window.
    $events = $this->getEventsInRange($events);

    $enforced_end = null;
    foreach ($parents as $key => $event) {
      $sequence_start = 0;
      $sequence_end = null;
      $start = null;

      $duration = $event->getDuration();

      $frequency = $event->getFrequencyUnit();
      $modify_key = '+1 '.$frequency;

      if (($this->rangeBegin !== null) &&
          ($this->rangeBegin > $event->getViewerDateFrom())) {
        $max_date = $this->rangeBegin - $duration;
        $date = $event->getViewerDateFrom();
        $datetime = PhabricatorTime::getDateTimeFromEpoch($date, $viewer);

        while ($date < $max_date) {
          // TODO: optimize this to not loop through all off-screen events
          $sequence_start++;
          $datetime = PhabricatorTime::getDateTimeFromEpoch($date, $viewer);
          $date = $datetime->modify($modify_key)->format('U');
        }

        $start = $this->rangeBegin;
      } else {
        $start = $event->getViewerDateFrom() - $duration;
      }

      $date = $start;
      $datetime = PhabricatorTime::getDateTimeFromEpoch($date, $viewer);

      // Select the minimum end time we need to generate events until.
      $end_times = array();
      if ($this->rangeEnd) {
        $end_times[] = $this->rangeEnd;
      }

      if ($event->getRecurrenceEndDate()) {
        $end_times[] = $event->getRecurrenceEndDate();
      }

      if ($enforced_end) {
        $end_times[] = $enforced_end;
      }

      if ($end_times) {
        $end = min($end_times);
        $sequence_end = $sequence_start;
        while ($date < $end) {
          $sequence_end++;
          $datetime->modify($modify_key);
          $date = $datetime->format('U');
          if ($sequence_end > $raw_limit + $sequence_start) {
            break;
          }
        }
      } else {
        $sequence_end = $raw_limit + $sequence_start;
      }

      $sequence_start = max(1, $sequence_start);
      for ($index = $sequence_start; $index < $sequence_end; $index++) {
        $events[] = $event->newGhost($viewer, $index);
      }

      // NOTE: We're slicing results every time because this makes it cheaper
      // to generate future ghosts. If we already have 100 events that occur
      // before July 1, we know we never need to generate ghosts after that
      // because they couldn't possibly ever appear in the result set.

      if ($raw_limit) {
        if (count($events) > $raw_limit) {
          $events = msort($events, 'getViewerDateFrom');
          $events = array_slice($events, 0, $raw_limit, true);
          $enforced_end = last($events)->getViewerDateFrom();
        }
      }
    }

    // Now that we're done generating ghost events, we're going to remove any
    // ghosts that we have concrete events for (or which we can load the
    // concrete events for). These concrete events are generated when users
    // edit a ghost, and replace the ghost events.

    // First, generate a map of all concrete <parentPHID, sequence> events we
    // already loaded. We don't need to load these again.
    $have_pairs = array();
    foreach ($events as $event) {
      if ($event->getIsGhostEvent()) {
        continue;
      }

      $parent_phid = $event->getInstanceOfEventPHID();
      $sequence = $event->getSequenceIndex();

      $have_pairs[$parent_phid][$sequence] = true;
    }

    // Now, generate a map of all <parentPHID, sequence> events we generated
    // ghosts for. We need to try to load these if we don't already have them.
    $map = array();
    $parent_pairs = array();
    foreach ($events as $key => $event) {
      if (!$event->getIsGhostEvent()) {
        continue;
      }

      $parent_phid = $event->getInstanceOfEventPHID();
      $sequence = $event->getSequenceIndex();

      // We already loaded the concrete version of this event, so we can just
      // throw out the ghost and move on.
      if (isset($have_pairs[$parent_phid][$sequence])) {
        unset($events[$key]);
        continue;
      }

      // We didn't load the concrete version of this event, so we need to
      // try to load it if it exists.
      $parent_pairs[] = array($parent_phid, $sequence);
      $map[$parent_phid][$sequence] = $key;
    }

    if ($parent_pairs) {
      $instances = id(new self())
        ->setViewer($viewer)
        ->setParentQuery($this)
        ->withInstanceSequencePairs($parent_pairs)
        ->execute();

      foreach ($instances as $instance) {
        $parent_phid = $instance->getInstanceOfEventPHID();
        $sequence = $instance->getSequenceIndex();

        $indexes = idx($map, $parent_phid);
        $key = idx($indexes, $sequence);

        // Replace the ghost with the corresponding concrete event.
        $events[$key] = $instance;
      }
    }

    $events = msort($events, 'getViewerDateFrom');

    return $events;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn_r) {
    $parts = parent::buildJoinClauseParts($conn_r);

    if ($this->inviteePHIDs !== null) {
      $parts[] = qsprintf(
        $conn_r,
        'JOIN %T invitee ON invitee.eventPHID = event.phid
          AND invitee.status != %s',
        id(new PhabricatorCalendarEventInvitee())->getTableName(),
        PhabricatorCalendarEventInvitee::STATUS_UNINVITED);
    }

    return $parts;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'event.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'event.phid IN (%Ls)',
        $this->phids);
    }

    // NOTE: The date ranges we query for are larger than the requested ranges
    // because we need to catch all-day events. We'll refine this range later
    // after adjusting the visible range of events we load.

    if ($this->rangeBegin) {
      $where[] = qsprintf(
        $conn,
        'event.dateTo >= %d OR event.isRecurring = 1',
        $this->rangeBegin - phutil_units('16 hours in seconds'));
    }

    if ($this->rangeEnd) {
      $where[] = qsprintf(
        $conn,
        'event.dateFrom <= %d',
        $this->rangeEnd + phutil_units('16 hours in seconds'));
    }

    if ($this->inviteePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'invitee.inviteePHID IN (%Ls)',
        $this->inviteePHIDs);
    }

    if ($this->hostPHIDs) {
      $where[] = qsprintf(
        $conn,
        'event.hostPHID IN (%Ls)',
        $this->hostPHIDs);
    }

    if ($this->isCancelled !== null) {
      $where[] = qsprintf(
        $conn,
        'event.isCancelled = %d',
        (int)$this->isCancelled);
    }

    if ($this->eventsWithNoParent == true) {
      $where[] = qsprintf(
        $conn,
        'event.instanceOfEventPHID IS NULL');
    }

    if ($this->instanceSequencePairs !== null) {
      $sql = array();

      foreach ($this->instanceSequencePairs as $pair) {
        $sql[] = qsprintf(
          $conn,
          '(event.instanceOfEventPHID = %s AND event.sequenceIndex = %d)',
          $pair[0],
          $pair[1]);
      }

      $where[] = qsprintf(
        $conn,
        '%Q',
        implode(' OR ', $sql));
    }

    if ($this->isStub !== null) {
      $where[] = qsprintf(
        $conn,
        'event.isStub = %d',
        (int)$this->isStub);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'event';
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->inviteePHIDs !== null) {
      return true;
    }
    return parent::shouldGroupQueryResultRows();
  }

  protected function getApplicationSearchObjectPHIDColumn() {
    return 'event.phid';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }


  protected function willFilterPage(array $events) {
    $instance_of_event_phids = array();
    $recurring_events = array();
    $viewer = $this->getViewer();

    $events = $this->getEventsInRange($events);

    $phids = array();

    foreach ($events as $event) {
      $phids[] = $event->getPHID();
      $instance_of = $event->getInstanceOfEventPHID();

      if ($instance_of) {
        $instance_of_event_phids[] = $instance_of;
      }
    }

    if (count($instance_of_event_phids) > 0) {
      $recurring_events = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->withPHIDs($instance_of_event_phids)
        ->withEventsWithNoParent(true)
        ->execute();

      $recurring_events = mpull($recurring_events, null, 'getPHID');
    }

    if ($events) {
      $invitees = id(new PhabricatorCalendarEventInviteeQuery())
        ->setViewer($viewer)
        ->withEventPHIDs($phids)
        ->execute();
      $invitees = mgroup($invitees, 'getEventPHID');
    } else {
      $invitees = array();
    }

    foreach ($events as $key => $event) {
      $event_invitees = idx($invitees, $event->getPHID(), array());
      $event->attachInvitees($event_invitees);

      $instance_of = $event->getInstanceOfEventPHID();
      if (!$instance_of) {
        continue;
      }
      $parent = idx($recurring_events, $instance_of);

      // should never get here
      if (!$parent) {
        unset($events[$key]);
        continue;
      }
      $event->attachParentEvent($parent);

      if ($this->isCancelled !== null) {
        if ($event->getIsCancelled() != $this->isCancelled) {
          unset($events[$key]);
          continue;
        }
      }
    }

    $events = msort($events, 'getViewerDateFrom');

    return $events;
  }

  private function getEventsInRange(array $events) {
    $range_start = $this->rangeBegin;
    $range_end = $this->rangeEnd;

    foreach ($events as $key => $event) {
      $event_start = $event->getViewerDateFrom();
      $event_end = $event->getViewerDateTo();

      if ($range_start && $event_end < $range_start) {
        unset($events[$key]);
      }

      if ($range_end && $event_start > $range_end) {
        unset($events[$key]);
      }
    }

    return $events;
  }

}

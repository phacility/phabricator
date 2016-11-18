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
  private $parentEventPHIDs;
  private $importSourcePHIDs;
  private $importAuthorPHIDs;
  private $importUIDs;
  private $utcInitialEpochMin;
  private $utcInitialEpochMax;
  private $isImported;
  private $needRSVPs;

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

  public function withUTCInitialEpochBetween($min, $max) {
    $this->utcInitialEpochMin = $min;
    $this->utcInitialEpochMax = $max;
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

  public function withParentEventPHIDs(array $parent_phids) {
    $this->parentEventPHIDs = $parent_phids;
    return $this;
  }

  public function withImportSourcePHIDs(array $import_phids) {
    $this->importSourcePHIDs = $import_phids;
    return $this;
  }

  public function withImportAuthorPHIDs(array $author_phids) {
    $this->importAuthorPHIDs = $author_phids;
    return $this;
  }

  public function withImportUIDs(array $uids) {
    $this->importUIDs = $uids;
    return $this;
  }

  public function withIsImported($is_imported) {
    $this->isImported = $is_imported;
    return $this;
  }

  public function needRSVPs(array $phids) {
    $this->needRSVPs = $phids;
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
        'column' => 'utcInitialEpoch',
        'reverse' => true,
        'type' => 'int',
        'unique' => false,
      ),
    ) + parent::getOrderableColumns();
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $event = $this->loadCursorObject($cursor);
    return array(
      'start' => $event->getStartDateTimeEpoch(),
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

    $generate_from = $this->rangeBegin;
    $generate_until = $this->rangeEnd;
    foreach ($parents as $key => $event) {
      $duration = $event->getDuration();

      $start_date = $this->getRecurrenceWindowStart(
        $event,
        $generate_from - $duration);

      $end_date = $this->getRecurrenceWindowEnd(
        $event,
        $generate_until);

      $limit = $this->getRecurrenceLimit($event, $raw_limit);

      $set = $event->newRecurrenceSet();

      $recurrences = $set->getEventsBetween(
        null,
        $end_date,
        $limit + 1);

      // We're generating events from the beginning and then filtering them
      // here (instead of only generating events starting at the start date)
      // because we need to know the proper sequence indexes to generate ghost
      // events. This may change after RDATE support.
      if ($start_date) {
        $start_epoch = $start_date->getEpoch();
      } else {
        $start_epoch = null;
      }

      foreach ($recurrences as $sequence_index => $sequence_datetime) {
        if (!$sequence_index) {
          // This is the parent event, which we already have.
          continue;
        }

        if ($start_epoch) {
          if ($sequence_datetime->getEpoch() < $start_epoch) {
            continue;
          }
        }

        $events[] = $event->newGhost(
          $viewer,
          $sequence_index,
          $sequence_datetime);
      }

      // NOTE: We're slicing results every time because this makes it cheaper
      // to generate future ghosts. If we already have 100 events that occur
      // before July 1, we know we never need to generate ghosts after that
      // because they couldn't possibly ever appear in the result set.

      if ($raw_limit) {
        if (count($events) > $raw_limit) {
          $events = msort($events, 'getStartDateTimeEpoch');
          $events = array_slice($events, 0, $raw_limit, true);
          $generate_until = last($events)->getEndDateTimeEpoch();
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

    $events = msort($events, 'getStartDateTimeEpoch');

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

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'event.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
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
        '(event.utcUntilEpoch >= %d) OR (event.utcUntilEpoch IS NULL)',
        $this->rangeBegin - phutil_units('16 hours in seconds'));
    }

    if ($this->rangeEnd) {
      $where[] = qsprintf(
        $conn,
        'event.utcInitialEpoch <= %d',
        $this->rangeEnd + phutil_units('16 hours in seconds'));
    }

    if ($this->utcInitialEpochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'event.utcInitialEpoch >= %d',
        $this->utcInitialEpochMin);
    }

    if ($this->utcInitialEpochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'event.utcInitialEpoch <= %d',
        $this->utcInitialEpochMax);
    }

    if ($this->inviteePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'invitee.inviteePHID IN (%Ls)',
        $this->inviteePHIDs);
    }

    if ($this->hostPHIDs !== null) {
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

    if ($this->parentEventPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'event.instanceOfEventPHID IN (%Ls)',
        $this->parentEventPHIDs);
    }

    if ($this->importSourcePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'event.importSourcePHID IN (%Ls)',
        $this->importSourcePHIDs);
    }

    if ($this->importAuthorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'event.importAuthorPHID IN (%Ls)',
        $this->importAuthorPHIDs);
    }

    if ($this->importUIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'event.importUID IN (%Ls)',
        $this->importUIDs);
    }

    if ($this->isImported !== null) {
      if ($this->isImported) {
        $where[] = qsprintf(
          $conn,
          'event.importSourcePHID IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn,
          'event.importSourcePHID IS NULL');
      }
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

    $import_phids = array();
    foreach ($events as $event) {
      $import_phid = $event->getImportSourcePHID();
      if ($import_phid !== null) {
        $import_phids[$import_phid] = $import_phid;
      }
    }

    if ($import_phids) {
      $imports = id(new PhabricatorCalendarImportQuery())
        ->setParentQuery($this)
        ->setViewer($viewer)
        ->withPHIDs($import_phids)
        ->execute();
      $imports = mpull($imports, null, 'getPHID');
    } else {
      $imports = array();
    }

    foreach ($events as $key => $event) {
      $import_phid = $event->getImportSourcePHID();
      if ($import_phid === null) {
        $event->attachImportSource(null);
        continue;
      }

      $import = idx($imports, $import_phid);
      if (!$import) {
        unset($events[$key]);
        $this->didRejectResult($event);
        continue;
      }

      $event->attachImportSource($import);
    }

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

    $events = msort($events, 'getStartDateTimeEpoch');

    if ($this->needRSVPs) {
      $rsvp_phids = $this->needRSVPs;
      $project_type = PhabricatorProjectProjectPHIDType::TYPECONST;

      $project_phids = array();
      foreach ($events as $event) {
        foreach ($event->getInvitees() as $invitee) {
          $invitee_phid = $invitee->getInviteePHID();
          if (phid_get_type($invitee_phid) == $project_type) {
            $project_phids[] = $invitee_phid;
          }
        }
      }

      if ($project_phids) {
        $member_type = PhabricatorProjectMaterializedMemberEdgeType::EDGECONST;

        $query = id(new PhabricatorEdgeQuery())
          ->withSourcePHIDs($project_phids)
          ->withEdgeTypes(array($member_type))
          ->withDestinationPHIDs($rsvp_phids);

        $edges = $query->execute();

        $project_map = array();
        foreach ($edges as $src => $types) {
          foreach ($types as $type => $dsts) {
            foreach ($dsts as $dst => $edge) {
              $project_map[$dst][] = $src;
            }
          }
        }
      } else {
        $project_map = array();
      }

      $membership_map = array();
      foreach ($rsvp_phids as $rsvp_phid) {
        $membership_map[$rsvp_phid] = array();
        $membership_map[$rsvp_phid][] = $rsvp_phid;

        $project_phids = idx($project_map, $rsvp_phid);
        if ($project_phids) {
          foreach ($project_phids as $project_phid) {
            $membership_map[$rsvp_phid][] = $project_phid;
          }
        }
      }

      foreach ($events as $event) {
        $invitees = $event->getInvitees();
        $invitees = mpull($invitees, null, 'getInviteePHID');

        $rsvp_map = array();
        foreach ($rsvp_phids as $rsvp_phid) {
          $membership_phids = $membership_map[$rsvp_phid];
          $rsvps = array_select_keys($invitees, $membership_phids);
          $rsvp_map[$rsvp_phid] = $rsvps;
        }

        $event->attachRSVPs($rsvp_map);
      }
    }

    return $events;
  }

  private function getEventsInRange(array $events) {
    $range_start = $this->rangeBegin;
    $range_end = $this->rangeEnd;

    foreach ($events as $key => $event) {
      $event_start = $event->getStartDateTimeEpoch();
      $event_end = $event->getEndDateTimeEpoch();

      if ($range_start && $event_end < $range_start) {
        unset($events[$key]);
      }

      if ($range_end && $event_start > $range_end) {
        unset($events[$key]);
      }
    }

    return $events;
  }

  private function getRecurrenceWindowStart(
    PhabricatorCalendarEvent $event,
    $generate_from) {

    if (!$generate_from) {
      return null;
    }

    return PhutilCalendarAbsoluteDateTime::newFromEpoch($generate_from);
  }

  private function getRecurrenceWindowEnd(
    PhabricatorCalendarEvent $event,
    $generate_until) {

    $end_epochs = array();
    if ($generate_until) {
      $end_epochs[] = $generate_until;
    }

    $until_epoch = $event->getUntilDateTimeEpoch();
    if ($until_epoch) {
      $end_epochs[] = $until_epoch;
    }

    if (!$end_epochs) {
      return null;
    }

    return PhutilCalendarAbsoluteDateTime::newFromEpoch(min($end_epochs));
  }

  private function getRecurrenceLimit(
    PhabricatorCalendarEvent $event,
    $raw_limit) {

    $count = $event->getRecurrenceCount();
    if ($count && ($count <= $raw_limit)) {
      return ($count - 1);
    }

    return $raw_limit;
  }

}

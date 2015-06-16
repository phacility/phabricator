<?php

final class PhabricatorCalendarEventQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $rangeBegin;
  private $rangeEnd;
  private $inviteePHIDs;
  private $creatorPHIDs;
  private $isCancelled;
  private $eventsWithNoParent;
  private $instanceSequencePairs;

  private $generateGhosts = false;

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

  public function withCreatorPHIDs(array $phids) {
    $this->creatorPHIDs = $phids;
    return $this;
  }

  public function withIsCancelled($is_cancelled) {
    $this->isCancelled = $is_cancelled;
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
      'start' => $event->getDateFrom(),
      'id' => $event->getID(),
    );
  }

  protected function loadPage() {
    $table = new PhabricatorCalendarEvent();
    $conn_r = $table->establishConnection('r');
    $viewer = $this->getViewer();

    $data = queryfx_all(
      $conn_r,
      'SELECT event.* FROM %T event %Q %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildGroupClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $events = $table->loadAllFromArray($data);

    foreach ($events as $event) {
      $event->applyViewerTimezone($this->getViewer());
    }

    if (!$this->generateGhosts) {
      return $events;
    }

    $enforced_end = null;

    foreach ($events as $key => $event) {
      $sequence_start = 0;
      $sequence_end = null;
      $duration = $event->getDateTo() - $event->getDateFrom();
      $end = null;

      $instance_of = $event->getInstanceOfEventPHID();

      if ($instance_of == null && $this->isCancelled !== null) {
        if ($event->getIsCancelled() != $this->isCancelled) {
          unset($events[$key]);
          continue;
        }
      }

      if ($event->getIsRecurring() && $instance_of == null) {
        $frequency = $event->getFrequencyUnit();
        $modify_key = '+1 '.$frequency;

        if ($this->rangeBegin && $this->rangeBegin > $event->getDateFrom()) {
          $max_date = $this->rangeBegin - $duration;
          $date = $event->getDateFrom();
          $datetime = PhabricatorTime::getDateTimeFromEpoch($date, $viewer);

          while ($date < $max_date) {
            // TODO: optimize this to not loop through all off-screen events
            $sequence_start++;
            $datetime = PhabricatorTime::getDateTimeFromEpoch($date, $viewer);
            $date = $datetime->modify($modify_key)->format('U');
          }

          $start = $this->rangeBegin;
        } else {
          $start = $event->getDateFrom() - $duration;
        }

        $date = $start;
        $datetime = PhabricatorTime::getDateTimeFromEpoch($date, $viewer);

        if (($this->rangeEnd && $event->getRecurrenceEndDate()) &&
          $this->rangeEnd < $event->getRecurrenceEndDate()) {
          $end = $this->rangeEnd;
        } else if ($event->getRecurrenceEndDate()) {
          $end = $event->getRecurrenceEndDate();
        } else if ($this->rangeEnd) {
          $end = $this->rangeEnd;
        } else if ($enforced_end) {
          if ($end) {
            $end = min($end, $enforced_end);
          } else {
            $end = $enforced_end;
          }
        }

        if ($end) {
          $sequence_end = $sequence_start;
          while ($date < $end) {
            $sequence_end++;
            $datetime->modify($modify_key);
            $date = $datetime->format('U');
            if ($sequence_end > $this->getRawResultLimit() + $sequence_start) {
              break;
            }
          }
        } else {
          $sequence_end = $this->getRawResultLimit() + $sequence_start;
        }

        $sequence_start = max(1, $sequence_start);

        for ($index = $sequence_start; $index < $sequence_end; $index++) {
          $events[] = $event->generateNthGhost($index, $viewer);
        }

        if (count($events) >= $this->getRawResultLimit()) {
          $events = msort($events, 'getDateFrom');
          $events = array_slice($events, 0, $this->getRawResultLimit(), true);
          $enforced_end = last($events)->getDateFrom();
        }
      }
    }

    $map = array();
    $instance_sequence_pairs = array();

    foreach ($events as $key => $event) {
      if ($event->getIsGhostEvent()) {
        $index = $event->getSequenceIndex();
        $instance_sequence_pairs[] = array($event->getPHID(), $index);
        $map[$event->getPHID()][$index] = $key;
      }
    }

    if (count($instance_sequence_pairs) > 0) {
      $sub_query = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->setParentQuery($this)
        ->withInstanceSequencePairs($instance_sequence_pairs)
        ->execute();

      foreach ($sub_query as $edited_ghost) {
        $indexes = idx($map, $edited_ghost->getInstanceOfEventPHID());
        $key = idx($indexes, $edited_ghost->getSequenceIndex());
        $events[$key] = $edited_ghost;
      }

      $id_map = array();
      foreach ($events as $key => $event) {
        if ($event->getIsGhostEvent()) {
          continue;
        }
        if (isset($id_map[$event->getID()])) {
          unset($events[$key]);
        } else {
          $id_map[$event->getID()] = true;
        }
      }
    }

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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'event.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'event.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->rangeBegin) {
      $where[] = qsprintf(
        $conn_r,
        'event.dateTo >= %d OR event.isRecurring = 1',
        $this->rangeBegin);
    }

    if ($this->rangeEnd) {
      $where[] = qsprintf(
        $conn_r,
        'event.dateFrom <= %d',
        $this->rangeEnd);
    }

    if ($this->inviteePHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'invitee.inviteePHID IN (%Ls)',
        $this->inviteePHIDs);
    }

    if ($this->creatorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'event.userPHID IN (%Ls)',
        $this->creatorPHIDs);
    }

    if ($this->isCancelled !== null) {
      $where[] = qsprintf(
        $conn_r,
        'event.isCancelled = %d',
        (int)$this->isCancelled);
    }

    if ($this->eventsWithNoParent == true) {
      $where[] = qsprintf(
        $conn_r,
        'event.instanceOfEventPHID IS NULL');
    }

    if ($this->instanceSequencePairs !== null) {
      $sql = array();

      foreach ($this->instanceSequencePairs as $pair) {
        $sql[] = qsprintf(
          $conn_r,
          '(event.instanceOfEventPHID = %s AND event.sequenceIndex = %d)',
          $pair[0],
          $pair[1]);
      }
      $where[] = qsprintf(
        $conn_r,
        '%Q',
        implode(' OR ', $sql));
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
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
    $range_start = $this->rangeBegin;
    $range_end = $this->rangeEnd;
    $instance_of_event_phids = array();
    $recurring_events = array();
    $viewer = $this->getViewer();

    foreach ($events as $key => $event) {
      $event_start = $event->getDateFrom();
      $event_end = $event->getDateTo();

      if ($range_start && $event_end < $range_start) {
        unset($events[$key]);
      }
      if ($range_end && $event_start > $range_end) {
        unset($events[$key]);
      }
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

    $events = msort($events, 'getDateFrom');

    return $events;
  }

}

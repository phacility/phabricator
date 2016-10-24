<?php

abstract class PhabricatorCalendarImportEngine
  extends Phobject {

  final public function getImportEngineType() {
    return $this->getPhobjectClassConstant('ENGINETYPE', 64);
  }


  abstract public function getImportEngineName();
  abstract public function getImportEngineTypeName();
  abstract public function getImportEngineHint();

  public function appendImportProperties(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import,
    PHUIPropertyListView $properties) {
    return;
  }

  abstract public function newEditEngineFields(
    PhabricatorEditEngine $engine,
    PhabricatorCalendarImport $import);

  abstract public function getDisplayName(PhabricatorCalendarImport $import);

  abstract public function didCreateImport(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import);

  abstract public function canDisable(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import);

  public function explainCanDisable(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import) {
    throw new PhutilMethodNotImplementedException();
  }

  final public static function getAllImportEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getImportEngineType')
      ->setSortMethod('getImportEngineName')
      ->execute();
  }

  final protected function importEventDocument(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import,
    PhutilCalendarRootNode $root = null) {

    $event_type = PhutilCalendarEventNode::NODETYPE;

    $nodes = array();
    if ($root) {
      foreach ($root->getChildren() as $document) {
        foreach ($document->getChildren() as $node) {
          $node_type = $node->getNodeType();
          if ($node_type != $event_type) {
            $import->newLogMessage(
              PhabricatorCalendarImportIgnoredNodeLogType::LOGTYPE,
              array(
                'node.type' => $node_type,
              ));
            continue;
          }

          $nodes[] = $node;
        }
      }
    }

    // Reject events which have dates outside of the range of a signed
    // 32-bit integer. We'll need to accommodate a wider range of events
    // eventually, but have about 20 years until it's an issue and we'll
    // all be dead by then.
    foreach ($nodes as $key => $node) {
      $dates = array();
      $dates[] = $node->getStartDateTime();
      $dates[] = $node->getEndDateTime();
      $dates[] = $node->getCreatedDateTime();
      $dates[] = $node->getModifiedDateTime();
      $rrule = $node->getRecurrenceRule();
      if ($rrule) {
        $dates[] = $rrule->getUntil();
      }

      $bad_date = false;
      foreach ($dates as $date) {
        if ($date === null) {
          continue;
        }

        $year = $date->getYear();
        if ($year < 1970 || $year > 2037) {
          $bad_date = true;
          break;
        }
      }

      if ($bad_date) {
        $import->newLogMessage(
          PhabricatorCalendarImportEpochLogType::LOGTYPE,
          array());
        unset($nodes[$key]);
      }
    }

    // Reject events which occur too frequently. Users do not normally define
    // these events and the UI and application make many assumptions which are
    // incompatible with events recurring once per second.
    foreach ($nodes as $key => $node) {
      $rrule = $node->getRecurrenceRule();
      if (!$rrule) {
        // This is not a recurring event, so we don't need to check the
        // frequency.
        continue;
      }
      $scale = $rrule->getFrequencyScale();
      if ($scale >= PhutilCalendarRecurrenceRule::SCALE_DAILY) {
        // This is a daily, weekly, monthly, or yearly event. These are
        // supported.
      } else {
        // This is an hourly, minutely, or secondly event.
        $import->newLogMessage(
          PhabricatorCalendarImportFrequencyLogType::LOGTYPE,
          array(
            'frequency' => $rrule->getFrequency(),
          ));
        unset($nodes[$key]);
      }
    }

    $node_map = array();
    foreach ($nodes as $node) {
      $full_uid = $this->getFullNodeUID($node);
      if (isset($node_map[$full_uid])) {
        $import->newLogMessage(
          PhabricatorCalendarImportDuplicateLogType::LOGTYPE,
          array(
            'uid.full' => $full_uid,
          ));
        continue;
      }
      $node_map[$full_uid] = $node;
    }

    // If we already know about some of these events and they were created
    // here, we're not going to import it again. This can happen if a user
    // exports an event and then tries to import it again. This is probably
    // not what they meant to do and this pathway generally leads to madness.
    $likely_phids = array();
    foreach ($node_map as $full_uid => $node) {
      $uid = $node->getUID();
      $matches = null;
      if (preg_match('/^(PHID-.*)@(.*)\z/', $uid, $matches)) {
        $likely_phids[$full_uid] = $matches[1];
      }
    }

    if ($likely_phids) {
      // NOTE: We're using the omnipotent viewer here because we don't want
      // to collide with events that already exist, even if you can't see
      // them.
      $events = id(new PhabricatorCalendarEventQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($likely_phids)
        ->execute();
      $events = mpull($events, null, 'getPHID');
      foreach ($node_map as $full_uid => $node) {
        $phid = idx($likely_phids, $full_uid);
        if (!$phid) {
          continue;
        }

        $event = idx($events, $phid);
        if (!$event) {
          continue;
        }

        $import->newLogMessage(
          PhabricatorCalendarImportOriginalLogType::LOGTYPE,
          array(
            'phid' => $event->getPHID(),
          ));

        unset($node_map[$full_uid]);
      }
    }

    if ($node_map) {
      $events = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->withImportAuthorPHIDs(array($viewer->getPHID()))
        ->withImportUIDs(array_keys($node_map))
        ->execute();
      $events = mpull($events, null, 'getImportUID');
    } else {
      $events = null;
    }

    $xactions = array();
    $update_map = array();
    foreach ($node_map as $full_uid => $node) {
      $event = idx($events, $full_uid);
      if (!$event) {
        $event = PhabricatorCalendarEvent::initializeNewCalendarEvent($viewer);
      }

      $event
        ->setImportAuthorPHID($viewer->getPHID())
        ->setImportSourcePHID($import->getPHID())
        ->setImportUID($full_uid)
        ->attachImportSource($import);

      $this->updateEventFromNode($viewer, $event, $node);
      $xactions[$full_uid] = $this->newUpdateTransactions($event, $node);
      $update_map[$full_uid] = $event;
    }

    // Reorder events so we create parents first. This allows us to populate
    // "instanceOfEventPHID" correctly.
    $insert_order = array();
    foreach ($update_map as $full_uid => $event) {
      $parent_uid = $this->getParentNodeUID($node_map[$full_uid]);
      if ($parent_uid === null) {
        $insert_order[$full_uid] = $full_uid;
        continue;
      }

      if (empty($update_map[$parent_uid])) {
        // The parent was not present in this import, which means it either
        // does not exist or we're going to delete it anyway. We just drop
        // this node.

        $import->newLogMessage(
          PhabricatorCalendarImportOrphanLogType::LOGTYPE,
          array(
            'uid.full' => $full_uid,
            'uid.parent' => $parent_uid,
          ));

        continue;
      }

      // Otherwise, we're going to insert the parent first, then insert
      // the child.
      $insert_order[$parent_uid] = $parent_uid;
      $insert_order[$full_uid] = $full_uid;
    }

    // TODO: Define per-engine content sources so this can say "via Upload" or
    // whatever.
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorWebContentSource::SOURCECONST);

    // NOTE: We're using the omnipotent user here because imported events are
    // otherwise immutable.
    $edit_actor = PhabricatorUser::getOmnipotentUser();

    $update_map = array_select_keys($update_map, $insert_order);
    foreach ($update_map as $full_uid => $event) {
      $parent_uid = $this->getParentNodeUID($node_map[$full_uid]);
      if ($parent_uid) {
        $parent_phid = $update_map[$full_uid]->getPHID();
      } else {
        $parent_phid = null;
      }

      $event->setInstanceOfEventPHID($parent_phid);

      $event_xactions = $xactions[$full_uid];

      $editor = id(new PhabricatorCalendarEventEditor())
        ->setActor($edit_actor)
        ->setActingAsPHID($import->getPHID())
        ->setContentSource($content_source)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $is_new = !$event->getID();

      $editor->applyTransactions($event, $event_xactions);

      $import->newLogMessage(
        PhabricatorCalendarImportUpdateLogType::LOGTYPE,
        array(
          'new' => $is_new,
          'phid' => $event->getPHID(),
        ));
    }

    if (!$update_map) {
      $import->newLogMessage(
        PhabricatorCalendarImportEmptyLogType::LOGTYPE,
        array());
    }

    // TODO: When the source is a subscription-based ICS file or some other
    // similar source, we should load all events from the source here and
    // destroy the ones we didn't update. These are events that have been
    // deleted.
  }

  private function getFullNodeUID(PhutilCalendarEventNode $node) {
    $uid = $node->getUID();
    $instance_epoch = $this->getNodeInstanceEpoch($node);
    $full_uid = $uid.'/'.$instance_epoch;

    return $full_uid;
  }

  private function getParentNodeUID(PhutilCalendarEventNode $node) {
    $recurrence_id = $node->getRecurrenceID();

    if (!strlen($recurrence_id)) {
      return null;
    }

    return $node->getUID().'/';
  }

  private function getNodeInstanceEpoch(PhutilCalendarEventNode $node) {
    $instance_iso = $node->getRecurrenceID();
    if (strlen($instance_iso)) {
      $instance_datetime = PhutilCalendarAbsoluteDateTime::newFromISO8601(
        $instance_iso);
      $instance_epoch = $instance_datetime->getEpoch();
    } else {
      $instance_epoch = null;
    }

    return $instance_epoch;
  }

  private function newUpdateTransactions(
    PhabricatorCalendarEvent $event,
    PhutilCalendarEventNode $node) {

    $xactions = array();
    $uid = $node->getUID();

    $name = $node->getName();
    if (!strlen($name)) {
      if (strlen($uid)) {
        $name = pht('Unnamed Event "%s"', $uid);
      } else {
        $name = pht('Unnamed Imported Event');
      }
    }
    $xactions[] = id(new PhabricatorCalendarEventTransaction())
      ->setTransactionType(
        PhabricatorCalendarEventNameTransaction::TRANSACTIONTYPE)
      ->setNewValue($name);

    $description = $node->getDescription();
    $xactions[] = id(new PhabricatorCalendarEventTransaction())
      ->setTransactionType(
        PhabricatorCalendarEventDescriptionTransaction::TRANSACTIONTYPE)
      ->setNewValue((string)$description);

    $is_recurring = (bool)$node->getRecurrenceRule();
    $xactions[] = id(new PhabricatorCalendarEventTransaction())
      ->setTransactionType(
        PhabricatorCalendarEventRecurringTransaction::TRANSACTIONTYPE)
      ->setNewValue($is_recurring);

    return $xactions;
  }

  private function updateEventFromNode(
    PhabricatorUser $actor,
    PhabricatorCalendarEvent $event,
    PhutilCalendarEventNode $node) {

    $instance_epoch = $this->getNodeInstanceEpoch($node);
    $event->setUTCInstanceEpoch($instance_epoch);

    $timezone = $actor->getTimezoneIdentifier();

    // TODO: These should be transactional, but the transaction only accepts
    // epoch timestamps right now.
    $start_datetime = $node->getStartDateTime()
      ->setViewerTimezone($timezone);
    $end_datetime = $node->getEndDateTime()
      ->setViewerTimezone($timezone);

    $event
      ->setStartDateTime($start_datetime)
      ->setEndDateTime($end_datetime);

    // TODO: This should be transactional, but the transaction only accepts
    // simple frequency rules right now.
    $rrule = $node->getRecurrenceRule();
    if ($rrule) {
      $event->setRecurrenceRule($rrule);

      $until_datetime = $rrule->getUntil();
      if ($until_datetime) {
        $until_datetime->setViewerTimezone($timezone);
        $event->setUntilDateTime($until_datetime);
      }
    }

    return $event;
  }

  public function canDeleteAnyEvents(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import) {

    $any_event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withImportSourcePHIDs(array($import->getPHID()))
      ->setLimit(1)
      ->execute();

    return (bool)$any_event;
  }

}

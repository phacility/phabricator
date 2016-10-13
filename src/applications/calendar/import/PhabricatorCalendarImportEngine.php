<?php

abstract class PhabricatorCalendarImportEngine
  extends Phobject {

  final public function getImportEngineType() {
    return $this->getPhobjectClassConstant('ENGINETYPE', 64);
  }


  abstract public function getImportEngineName();
  abstract public function getImportEngineHint();

  abstract public function newEditEngineFields(
    PhabricatorEditEngine $engine,
    PhabricatorCalendarImport $import);

  abstract public function getDisplayName(PhabricatorCalendarImport $import);

  abstract public function didCreateImport(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import);

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
    PhutilCalendarRootNode $root) {

    $event_type = PhutilCalendarEventNode::NODETYPE;

    $nodes = array();
    foreach ($root->getChildren() as $document) {
      foreach ($document->getChildren() as $node) {
        if ($node->getNodeType() != $event_type) {
          // TODO: Warn that we ignored this.
          continue;
        }

        $nodes[] = $node;
      }
    }

    $node_map = array();
    $parent_uids = array();
    foreach ($nodes as $node) {
      $full_uid = $this->getFullNodeUID($node);
      if (isset($node_map[$full_uid])) {
        // TODO: Warn that we got a duplicate.
        continue;
      }
      $node_map[$full_uid] = $node;
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

        // TODO: Warn that we got rid of an event with no parent.

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
        ->setActor($viewer)
        ->setActingAsPHID($import->getPHID())
        ->setContentSource($content_source)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($event, $event_xactions);
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

      $until_datetime = $rrule->getUntil()
        ->setViewerTimezone($timezone);
      if ($until_datetime) {
        $event->setUntilDateTime($until_datetime);
      }
    }

    return $event;
  }

}

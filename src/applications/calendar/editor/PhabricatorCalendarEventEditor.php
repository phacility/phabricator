<?php

final class PhabricatorCalendarEventEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Calendar');
  }

  protected function shouldApplyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function applyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $actor = $this->requireActor();
    if ($object->getIsStub()) {
      $this->materializeStub($object);
    }
  }

  private function materializeStub(PhabricatorCalendarEvent $event) {
    if (!$event->getIsStub()) {
      throw new Exception(
        pht('Can not materialize an event stub: this event is not a stub.'));
    }

    $actor = $this->getActor();
    $event->copyFromParent($actor);
    $event->setIsStub(0);

    $invitees = $event->getParentEvent()->getInvitees();

    $new_invitees = array();
    foreach ($invitees as $invitee) {
      $invitee = id(new PhabricatorCalendarEventInvitee())
        ->setEventPHID($event->getPHID())
        ->setInviteePHID($invitee->getInviteePHID())
        ->setInviterPHID($invitee->getInviterPHID())
        ->setStatus($invitee->getStatus())
        ->save();

      $new_invitees[] = $invitee;
    }

    $event->save();
    $event->attachInvitees($new_invitees);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function adjustObjectForPolicyChecks(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $copy = parent::adjustObjectForPolicyChecks($object, $xactions);
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorCalendarEventHostTransaction::TRANSACTIONTYPE:
          $copy->setHostPHID($xaction->getNewValue());
          break;
        case PhabricatorCalendarEventInviteTransaction::TRANSACTIONTYPE:
          PhabricatorPolicyRule::passTransactionHintToRule(
            $copy,
            new PhabricatorCalendarEventInviteesPolicyRule(),
            array_fuse($xaction->getNewValue()));
          break;
      }
    }

    return $copy;
  }


  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Clear the availability caches for users whose availability is affected
    // by this edit.

    $invalidate_all = false;
    $invalidate_phids = array();
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorCalendarEventUntilDateTransaction::TRANSACTIONTYPE:
        case PhabricatorCalendarEventStartDateTransaction::TRANSACTIONTYPE:
        case PhabricatorCalendarEventEndDateTransaction::TRANSACTIONTYPE:
        case PhabricatorCalendarEventCancelTransaction::TRANSACTIONTYPE:
        case PhabricatorCalendarEventAllDayTransaction::TRANSACTIONTYPE:
          // For these kinds of changes, we need to invalidate the availabilty
          // caches for all attendees.
          $invalidate_all = true;
          break;
        case PhabricatorCalendarEventAcceptTransaction::TRANSACTIONTYPE:
        case PhabricatorCalendarEventDeclineTransaction::TRANSACTIONTYPE:
          $acting_phid = $this->getActingAsPHID();
          $invalidate_phids[$acting_phid] = $acting_phid;
          break;
        case PhabricatorCalendarEventInviteTransaction::TRANSACTIONTYPE:
          foreach ($xaction->getNewValue() as $phid => $ignored) {
            $invalidate_phids[$phid] = $phid;
          }
          break;
      }
    }

    $phids = mpull($object->getInvitees(), 'getInviteePHID');
    $phids = array_fuse($phids);

    if (!$invalidate_all) {
      $phids = array_select_keys($phids, $invalidate_phids);
    }

    if ($phids) {
      $object->applyViewerTimezone($this->getActor());

      $user = new PhabricatorUser();
      $conn_w = $user->establishConnection('w');
      queryfx(
        $conn_w,
        'UPDATE %T SET availabilityCacheTTL = NULL
          WHERE phid IN (%Ls) AND availabilityCacheTTL >= %d',
        $user->getTableName(),
        $phids,
        $object->getDateFromForCache());
    }

    return $xactions;
  }


  protected function validateAllTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $start_date_xaction =
      PhabricatorCalendarEventStartDateTransaction::TRANSACTIONTYPE;
    $end_date_xaction =
      PhabricatorCalendarEventEndDateTransaction::TRANSACTIONTYPE;
    $is_recurrence_xaction =
      PhabricatorCalendarEventRecurringTransaction::TRANSACTIONTYPE;
    $recurrence_end_xaction =
      PhabricatorCalendarEventUntilDateTransaction::TRANSACTIONTYPE;

    $start_date = $object->getDateFrom();
    $end_date = $object->getDateTo();
    $recurrence_end = $object->getRecurrenceEndDate();
    $is_recurring = $object->getIsRecurring();

    $errors = array();

    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $start_date_xaction) {
        $start_date = $xaction->getNewValue()->getEpoch();
      } else if ($xaction->getTransactionType() == $end_date_xaction) {
        $end_date = $xaction->getNewValue()->getEpoch();
      } else if ($xaction->getTransactionType() == $recurrence_end_xaction) {
        $recurrence_end = $xaction->getNewValue()->getEpoch();
      } else if ($xaction->getTransactionType() == $is_recurrence_xaction) {
        $is_recurring = $xaction->getNewValue();
      }
    }

    if ($start_date > $end_date) {
      $errors[] = new PhabricatorApplicationTransactionValidationError(
        $end_date_xaction,
        pht('Invalid'),
        pht('End date must be after start date.'),
        null);
    }

    if ($recurrence_end && !$is_recurring) {
      $errors[] = new PhabricatorApplicationTransactionValidationError(
        $recurrence_end_xaction,
        pht('Invalid'),
        pht('Event must be recurring to have a recurrence end date.').
        null);
    }

    return $errors;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Calendar]');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();

    if ($object->getHostPHID()) {
      $phids[] = $object->getHostPHID();
    }
    $phids[] = $this->getActingAsPHID();

    $invitees = $object->getInvitees();
    foreach ($invitees as $invitee) {
      $status = $invitee->getStatus();
      if ($status === PhabricatorCalendarEventInvitee::STATUS_ATTENDING
        || $status === PhabricatorCalendarEventInvitee::STATUS_INVITED) {
        $phids[] = $invitee->getInviteePHID();
      }
    }

    $phids = array_unique($phids);

    return $phids;
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorCalendarEventTransaction::MAILTAG_CONTENT =>
        pht(
          "An event's name, status, invite list, ".
          "icon, and description changes."),
      PhabricatorCalendarEventTransaction::MAILTAG_RESCHEDULE =>
        pht(
          "An event's start and end date ".
          "and cancellation status changes."),
      PhabricatorCalendarEventTransaction::MAILTAG_OTHER =>
        pht('Other event activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorCalendarReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("E{$id}: {$name}")
      ->addHeader('Thread-Topic', "E{$id}: ".$object->getName());
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $description = $object->getDescription();
    $body = parent::buildMailBody($object, $xactions);

    if (strlen($description)) {
      $body->addRemarkupSection(
        pht('EVENT DESCRIPTION'),
        $description);
    }

    $body->addLinkSection(
      pht('EVENT DETAIL'),
      PhabricatorEnv::getProductionURI('/E'.$object->getID()));

    $ics_attachment = $this->newICSAttachment($object);
    $body->addAttachment($ics_attachment);

    return $body;
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return id(new PhabricatorCalendarEventHeraldAdapter())
      ->setObject($object);
  }

  private function newICSAttachment(
    PhabricatorCalendarEvent $event) {
    $actor = $this->getActor();

    $event_node = $event->newIntermediateEventNode($actor);

    $document_node = id(new PhutilCalendarDocumentNode())
      ->appendChild($event_node);

    $root_node = id(new PhutilCalendarRootNode())
      ->appendChild($document_node);

    $ics_data = id(new PhutilICSWriter())
      ->writeICSDocument($root_node);

    $ics_attachment = new PhabricatorMetaMTAAttachment(
      $ics_data,
      $event->getICSFilename(),
      'text/calendar');

    return $ics_attachment;
  }

}

<?php

final class PhabricatorCalendarEventEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Calendar');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorCalendarEventTransaction::TYPE_NAME;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_START_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_END_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_STATUS;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_CANCEL;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_INVITE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_ALL_DAY;

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        return $object->getDateFrom();
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        return $object->getDateTo();
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        $status = $object->getStatus();
        if ($status === null) {
          return null;
        }
        return (int)$status;
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
        return $object->getIsCancelled();
      case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
        return (int)$object->getIsAllDay();
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        $map = $xaction->getNewValue();
        $phids = array_keys($map);
        $invitees = array();

        if ($map && !$this->getIsNewObject()) {
          $invitees = id(new PhabricatorCalendarEventInviteeQuery())
            ->setViewer($this->getActor())
            ->withEventPHIDs(array($object->getPHID()))
            ->withInviteePHIDs($phids)
            ->execute();
          $invitees = mpull($invitees, null, 'getInviteePHID');
        }

        $old = array();
        foreach ($phids as $phid) {
          $invitee = idx($invitees, $phid);
          if ($invitee) {
            $old[$phid] = $invitee->getStatus();
          } else {
            $old[$phid] = PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
          }
        }
        return $old;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        return $xaction->getNewValue();
      case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
        return (int)$xaction->getNewValue();
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        return (int)$xaction->getNewValue();
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        return $xaction->getNewValue()->getEpoch();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        $object->setDateFrom($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        $object->setDateTo($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
        $object->setIsCancelled((int)$xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
        $object->setIsAllDay((int)$xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
      case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
        return;
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        $map = $xaction->getNewValue();
        $phids = array_keys($map);
        $invitees = $object->getInvitees();
        $invitees = mpull($invitees, null, 'getInviteePHID');

        foreach ($phids as $phid) {
          $invitee = idx($invitees, $phid);
          if (!$invitee) {
            $invitee = id(new PhabricatorCalendarEventInvitee())
              ->setEventPHID($object->getPHID())
              ->setInviteePHID($phid)
              ->setInviterPHID($this->getActingAsPHID());
            $invitees[] = $invitee;
          }
          $invitee->setStatus($map[$phid])
            ->save();
        }
        $object->attachInvitees($invitees);
        return;
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function didApplyInternalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $object->removeViewerTimezone($this->requireActor());

    return $xactions;
  }


  protected function validateAllTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {
    $start_date_xaction = PhabricatorCalendarEventTransaction::TYPE_START_DATE;
    $end_date_xaction = PhabricatorCalendarEventTransaction::TYPE_END_DATE;
    $start_date = $object->getDateFrom();
    $end_date = $object->getDateTo();
    $errors = array();

    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $start_date_xaction) {
        $start_date = $xaction->getNewValue()->getEpoch();
      } else if ($xaction->getTransactionType() == $end_date_xaction) {
        $end_date = $xaction->getNewValue()->getEpoch();
      }
    }
    if ($start_date > $end_date) {
      $type = PhabricatorCalendarEventTransaction::TYPE_END_DATE;
      $errors[] = new PhabricatorApplicationTransactionValidationError(
        $type,
        pht('Invalid'),
        pht('End date must be after start date.'),
        null);
    }

    return $errors;
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Event name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        foreach ($xactions as $xaction) {
          $date_value = $xaction->getNewValue();
          if (!$date_value->isValid()) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('Invalid date.'),
              $xaction);
          }
        }
        break;
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

    $xactions = mfilter($xactions, 'shouldHide', true);
    return $xactions;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Calendar]');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();

    if ($object->getUserPHID()) {
      $phids[] = $object->getUserPHID();
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
          "and description changes."),
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
      $body->addTextSection(
        pht('EVENT DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('EVENT DETAIL'),
      PhabricatorEnv::getProductionURI('/E'.$object->getID()));


    return $body;
  }


}

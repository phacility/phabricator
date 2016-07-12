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

    $types[] = PhabricatorCalendarEventTransaction::TYPE_NAME;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_START_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_END_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_CANCEL;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_INVITE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_ALL_DAY;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_ICON;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_ACCEPT;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_DECLINE;

    $types[] = PhabricatorCalendarEventTransaction::TYPE_RECURRING;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_FREQUENCY;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE;

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_RECURRING:
        return (int)$object->getIsRecurring();
      case PhabricatorCalendarEventTransaction::TYPE_FREQUENCY:
        return $object->getFrequencyUnit();
      case PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE:
        return $object->getRecurrenceEndDate();
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        return $object->getDateFrom();
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        return $object->getDateTo();
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
        return $object->getIsCancelled();
      case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
        return (int)$object->getIsAllDay();
      case PhabricatorCalendarEventTransaction::TYPE_ICON:
        return $object->getIcon();
      case PhabricatorCalendarEventTransaction::TYPE_ACCEPT:
      case PhabricatorCalendarEventTransaction::TYPE_DECLINE:
        $actor_phid = $this->getActingAsPHID();
        return $object->getUserInviteStatus($actor_phid);
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        $invitees = $object->getInvitees();
        return mpull($invitees, 'getStatus', 'getInviteePHID');
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_FREQUENCY:
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
      case PhabricatorCalendarEventTransaction::TYPE_ICON:
        return $xaction->getNewValue();
      case PhabricatorCalendarEventTransaction::TYPE_ACCEPT:
        return PhabricatorCalendarEventInvitee::STATUS_ATTENDING;
      case PhabricatorCalendarEventTransaction::TYPE_DECLINE:
        return PhabricatorCalendarEventInvitee::STATUS_DECLINED;
      case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
      case PhabricatorCalendarEventTransaction::TYPE_RECURRING:
        return (int)$xaction->getNewValue();
      case PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        return $xaction->getNewValue()->getEpoch();
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        $status_invited = PhabricatorCalendarEventInvitee::STATUS_INVITED;
        $status_uninvited = PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
        $status_attending = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;

        $invitees = $object->getInvitees();
        foreach ($invitees as $key => $invitee) {
          if ($invitee->getStatus() == $status_uninvited) {
            unset($invitees[$key]);
          }
        }
        $invitees = mpull($invitees, null, 'getInviteePHID');

        $new = $xaction->getNewValue();
        $new = array_fuse($new);

        $all = array_keys($invitees + $new);
        $map = array();
        foreach ($all as $phid) {
          $is_old = isset($invitees[$phid]);
          $is_new = isset($new[$phid]);

          if ($is_old && !$is_new) {
            $map[$phid] = $status_uninvited;
          } else if (!$is_old && $is_new) {
            $map[$phid] = $status_invited;
          } else {
            $map[$phid] = $invitees[$phid]->getStatus();
          }
        }

        // If we're creating this event and the actor is inviting themselves,
        // mark them as attending.
        if ($this->getIsNewObject()) {
          $acting_phid = $this->getActingAsPHID();
          if (isset($map[$acting_phid])) {
            $map[$acting_phid] = $status_attending;
          }
        }

        return $map;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_RECURRING:
        return $object->setIsRecurring((int)$xaction->getNewValue());
      case PhabricatorCalendarEventTransaction::TYPE_FREQUENCY:
        return $object->setRecurrenceFrequency(
          array(
            'rule' => $xaction->getNewValue(),
          ));
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        $object->setDateFrom($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        $object->setDateTo($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE:
        $object->setRecurrenceEndDate($xaction->getNewValue());
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
      case PhabricatorCalendarEventTransaction::TYPE_ICON:
        $object->setIcon($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
      case PhabricatorCalendarEventTransaction::TYPE_ACCEPT:
      case PhabricatorCalendarEventTransaction::TYPE_DECLINE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_RECURRING:
      case PhabricatorCalendarEventTransaction::TYPE_FREQUENCY:
      case PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
      case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
      case PhabricatorCalendarEventTransaction::TYPE_ICON:
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
      case PhabricatorCalendarEventTransaction::TYPE_ACCEPT:
      case PhabricatorCalendarEventTransaction::TYPE_DECLINE:
        $acting_phid = $this->getActingAsPHID();

        $invitees = $object->getInvitees();
        $invitees = mpull($invitees, null, 'getInviteePHID');

        $invitee = idx($invitees, $acting_phid);
        if (!$invitee) {
          $invitee = id(new PhabricatorCalendarEventInvitee())
            ->setEventPHID($object->getPHID())
            ->setInviteePHID($acting_phid)
            ->setInviterPHID($acting_phid);
          $invitees[$acting_phid] = $invitee;
        }

        $invitee
          ->setStatus($xaction->getNewValue())
          ->save();

        $object->attachInvitees($invitees);
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
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
        case PhabricatorCalendarEventTransaction::TYPE_ICON:
          break;
        case PhabricatorCalendarEventTransaction::TYPE_RECURRING:
        case PhabricatorCalendarEventTransaction::TYPE_FREQUENCY:
        case PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE:
        case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
        case PhabricatorCalendarEventTransaction::TYPE_ALL_DAY:
          // For these kinds of changes, we need to invalidate the availabilty
          // caches for all attendees.
          $invalidate_all = true;
          break;

        case PhabricatorCalendarEventTransaction::TYPE_ACCEPT:
        case PhabricatorCalendarEventTransaction::TYPE_DECLINE:
          $acting_phid = $this->getActingAsPHID();
          $invalidate_phids[$acting_phid] = $acting_phid;
          break;
        case PhabricatorCalendarEventTransaction::TYPE_INVITE:
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
      PhabricatorCalendarEventTransaction::TYPE_START_DATE;
    $end_date_xaction =
      PhabricatorCalendarEventTransaction::TYPE_END_DATE;
    $is_recurrence_xaction =
      PhabricatorCalendarEventTransaction::TYPE_RECURRING;
    $recurrence_end_xaction =
      PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE;

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
      $type = PhabricatorCalendarEventTransaction::TYPE_END_DATE;
      $errors[] = new PhabricatorApplicationTransactionValidationError(
        $type,
        pht('Invalid'),
        pht('End date must be after start date.'),
        null);
    }

    if ($recurrence_end && !$is_recurring) {
      $type =
        PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE;
      $errors[] = new PhabricatorApplicationTransactionValidationError(
        $type,
        pht('Invalid'),
        pht('Event must be recurring to have a recurrence end date.').
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
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        $old = $object->getInvitees();
        $old = mpull($old, null, 'getInviteePHID');
        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();
          $new = array_fuse($new);
          $add = array_diff_key($new, $old);
          if (!$add) {
            continue;
          }

          // In the UI, we only allow you to invite mailable objects, but there
          // is no definitive marker for "invitable object" today. Just allow
          // any valid object to be invited.
          $objects = id(new PhabricatorObjectQuery())
            ->setViewer($this->getActor())
            ->withPHIDs($add)
            ->execute();
          $objects = mpull($objects, null, 'getPHID');
          foreach ($add as $phid) {
            if (isset($objects[$phid])) {
              continue;
            }

            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Invitee "%s" identifies an object that does not exist or '.
                'which you do not have permission to view.',
                $phid));
          }
        }
        break;
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE:
        foreach ($xactions as $xaction) {
          if ($xaction->getNewValue()->isValid()) {
            continue;
          }

          switch ($type) {
            case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
              $message = pht('Start date is invalid.');
              break;
            case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
              $message = pht('End date is invalid.');
              break;
            case PhabricatorCalendarEventTransaction::TYPE_RECURRENCE_END_DATE:
              $message = pht('Repeat until date is invalid.');
              break;
          }

          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            $message,
            $xaction);
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
    return true;
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


    return $body;
  }


}

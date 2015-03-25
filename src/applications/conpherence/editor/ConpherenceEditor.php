<?php

final class ConpherenceEditor extends PhabricatorApplicationTransactionEditor {

  const ERROR_EMPTY_PARTICIPANTS = 'error-empty-participants';
  const ERROR_EMPTY_MESSAGE = 'error-empty-message';

  public function getEditorApplicationClass() {
    return 'PhabricatorConpherenceApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Conpherence Threads');
  }

  public static function createConpherence(
    PhabricatorUser $creator,
    array $participant_phids,
    $title,
    $message,
    PhabricatorContentSource $source) {

    $conpherence = ConpherenceThread::initializeNewThread($creator);
    $files = array();
    $errors = array();
    if (empty($participant_phids)) {
      $errors[] = self::ERROR_EMPTY_PARTICIPANTS;
    } else {
      $participant_phids[] = $creator->getPHID();
      $participant_phids = array_unique($participant_phids);
      $conpherence->setRecentParticipantPHIDs(
        array_slice($participant_phids, 0, 10));
    }

    if (empty($message)) {
      $errors[] = self::ERROR_EMPTY_MESSAGE;
    }

    $file_phids = PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
      $creator,
      array($message));
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($creator)
        ->withPHIDs($file_phids)
        ->execute();
    }

    if (!$errors) {
      $xactions = array();
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceTransactionType::TYPE_PARTICIPANTS)
        ->setNewValue(array('+' => $participant_phids));
      if ($files) {
        $xactions[] = id(new ConpherenceTransaction())
          ->setTransactionType(ConpherenceTransactionType::TYPE_FILES)
          ->setNewValue(array('+' => mpull($files, 'getPHID')));
      }
      if ($title) {
        $xactions[] = id(new ConpherenceTransaction())
          ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
          ->setNewValue($title);
      }

      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new ConpherenceTransactionComment())
          ->setContent($message)
          ->setConpherencePHID($conpherence->getPHID()));

      id(new ConpherenceEditor())
        ->setContentSource($source)
        ->setContinueOnNoEffect(true)
        ->setActor($creator)
        ->applyTransactions($conpherence, $xactions);
    }

    return array($errors, $conpherence);
  }

  public function generateTransactionsFromText(
    PhabricatorUser $viewer,
    ConpherenceThread $conpherence,
    $text) {

    $files = array();
    $file_phids = PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
      $viewer,
      array($text));
    // Since these are extracted from text, we might be re-including the
    // same file -- e.g. a mock under discussion. Filter files we
    // already have.
    $existing_file_phids = $conpherence->getFilePHIDs();
    $file_phids = array_diff($file_phids, $existing_file_phids);
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($this->getActor())
        ->withPHIDs($file_phids)
        ->execute();
    }
    $xactions = array();
    if ($files) {
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceTransactionType::TYPE_FILES)
        ->setNewValue(array('+' => mpull($files, 'getPHID')));
    }
    $xactions[] = id(new ConpherenceTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new ConpherenceTransactionComment())
        ->setContent($text)
        ->setConpherencePHID($conpherence->getPHID()));
    return $xactions;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    $types[] = ConpherenceTransactionType::TYPE_TITLE;
    $types[] = ConpherenceTransactionType::TYPE_PARTICIPANTS;
    $types[] = ConpherenceTransactionType::TYPE_FILES;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_JOIN_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
        return $object->getTitle();
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        return $object->getParticipantPHIDs();
      case ConpherenceTransactionType::TYPE_FILES:
        return $object->getFilePHIDs();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
        return $xaction->getNewValue();
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
      case ConpherenceTransactionType::TYPE_FILES:
        return $this->getPHIDTransactionNewValue($xaction);
    }
  }

  /**
   * We really only need a read lock if we have a comment. In that case, we
   * must update the messagesCount field on the conpherence and
   * seenMessagesCount(s) for the participant(s).
   */
  protected function shouldReadLock(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $lock = false;
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $lock =  true;
        break;
    }

    return $lock;
  }

  /**
   * We need to apply initial effects IFF the conpherence is new. We must
   * save the conpherence first thing to make sure we have an id and a phid.
   */
  protected function shouldApplyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return !$object->getID();
  }

  protected function applyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $object->save();
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $object->setMessageCount((int)$object->getMessageCount() + 1);
        break;
      case ConpherenceTransactionType::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        break;
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        // If this is a new ConpherenceThread, we have to create the
        // participation data asap to pass policy checks. For existing
        // ConpherenceThreads, the existing participation is correct
        // at this stage. Note that later in applyCustomExternalTransaction
        // this participation data will be updated, particularly the
        // behindTransactionPHID which is just a generated dummy for now.
        if ($this->getIsNewObject()) {
          $participants = array();
          foreach ($xaction->getNewValue() as $phid) {
            if ($phid == $this->getActor()->getPHID()) {
              $status = ConpherenceParticipationStatus::UP_TO_DATE;
              $message_count = 1;
            } else {
              $status = ConpherenceParticipationStatus::BEHIND;
              $message_count = 0;
            }
            $participants[$phid] =
              id(new ConpherenceParticipant())
              ->setConpherencePHID($object->getPHID())
              ->setParticipantPHID($phid)
              ->setParticipationStatus($status)
              ->setDateTouched(time())
              ->setBehindTransactionPHID($xaction->generatePHID())
              ->setSeenMessageCount($message_count)
              ->save();
            $object->attachParticipants($participants);
          }
        }
        break;
    }
    $this->updateRecentParticipantPHIDs($object, $xaction);
  }

  private function updateRecentParticipantPHIDs(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $participants = $object->getRecentParticipantPHIDs();
    array_unshift($participants, $xaction->getAuthorPHID());
    $participants = array_slice(array_unique($participants), 0, 10);

    $object->setRecentParticipantPHIDs($participants);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_FILES:
        $editor = new PhabricatorEdgeEditor();
        $edge_type = PhabricatorObjectHasFileEdgeType::EDGECONST;
        $old = array_fill_keys($xaction->getOldValue(), true);
        $new = array_fill_keys($xaction->getNewValue(), true);
        $add_edges = array_keys(array_diff_key($new, $old));
        $remove_edges = array_keys(array_diff_key($old, $new));
        foreach ($add_edges as $file_phid) {
          $editor->addEdge(
            $object->getPHID(),
            $edge_type,
            $file_phid);
        }
        foreach ($remove_edges as $file_phid) {
          $editor->removeEdge(
            $object->getPHID(),
            $edge_type,
            $file_phid);
        }
        $editor->save();
        break;
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        $participants = $object->getParticipants();

        $old_map = array_fuse($xaction->getOldValue());
        $new_map = array_fuse($xaction->getNewValue());

        $remove = array_keys(array_diff_key($old_map, $new_map));
        foreach ($remove as $phid) {
          $remove_participant = $participants[$phid];
          $remove_participant->delete();
          unset($participants[$phid]);
        }

        $add = array_keys(array_diff_key($new_map, $old_map));
        foreach ($add as $phid) {
          if ($this->getIsNewObject()) {
            $participants[$phid]
              ->setBehindTransactionPHID($xaction->getPHID())
              ->save();
          } else {
            if ($phid == $this->getActor()->getPHID()) {
              $status = ConpherenceParticipationStatus::UP_TO_DATE;
              $message_count = $object->getMessageCount();
            } else {
              $status = ConpherenceParticipationStatus::BEHIND;
              $message_count = 0;
            }
            $participants[$phid] =
              id(new ConpherenceParticipant())
              ->setConpherencePHID($object->getPHID())
              ->setParticipantPHID($phid)
              ->setParticipationStatus($status)
              ->setDateTouched(time())
              ->setBehindTransactionPHID($xaction->getPHID())
              ->setSeenMessageCount($message_count)
              ->save();
          }
        }
        $object->attachParticipants($participants);
        break;
    }
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $message_count = 0;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_COMMENT:
          $message_count++;
          break;
      }
    }

    // update everyone's participation status on the last xaction -only-
    $xaction = end($xactions);
    $xaction_phid = $xaction->getPHID();
    $behind = ConpherenceParticipationStatus::BEHIND;
    $up_to_date = ConpherenceParticipationStatus::UP_TO_DATE;
    $participants = $object->getParticipants();
    $user = $this->getActor();
    $time = time();
    foreach ($participants as $phid => $participant) {
      if ($phid != $user->getPHID()) {
        if ($participant->getParticipationStatus() != $behind) {
          $participant->setBehindTransactionPHID($xaction_phid);
          $participant->setSeenMessageCount(
            $object->getMessageCount() - $message_count);
        }
        $participant->setParticipationStatus($behind);
        $participant->setDateTouched($time);
      } else {
        $participant->setSeenMessageCount($object->getMessageCount());
        $participant->setParticipationStatus($up_to_date);
        $participant->setDateTouched($time);
      }
      $participant->save();
    }

    if ($xactions) {
      $data = array(
        'type'        => 'message',
        'threadPHID'  => $object->getPHID(),
        'messageID'   => last($xactions)->getID(),
        'subscribers' => array($object->getPHID()),
      );

      PhabricatorNotificationClient::tryToPostMessage($data);
    }

    return $xactions;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case ConpherenceTransactionType::TYPE_TITLE:
        return $v;
      case ConpherenceTransactionType::TYPE_FILES:
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        return $this->mergePHIDOrEdgeTransactions($u, $v);
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new ConpherenceReplyHandler())
      ->setActor($this->getActor())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();
    if (!$title) {
      $title = pht(
        '%s sent you a message.',
        $this->getActor()->getUserName());
    }
    $phid = $object->getPHID();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("E{$id}: {$title}")
      ->addHeader('Thread-Topic', "E{$id}: {$phid}");
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $to_phids = array();
    $participants = $object->getParticipants();
    if (empty($participants)) {
      return $to_phids;
    }
    $preferences = id(new PhabricatorUserPreferences())
      ->loadAllWhere('userPHID in (%Ls)', array_keys($participants));
    $preferences = mpull($preferences, null, 'getUserPHID');
    foreach ($participants as $phid => $participant) {
      $default = ConpherenceSettings::EMAIL_ALWAYS;
      $preference = idx($preferences, $phid);
      if ($preference) {
        $default = $preference->getPreference(
          PhabricatorUserPreferences::PREFERENCE_CONPH_NOTIFICATIONS,
          ConpherenceSettings::EMAIL_ALWAYS);
      }
      $settings = $participant->getSettings();
      $notifications = idx(
        $settings,
        'notifications',
        $default);
      if ($notifications == ConpherenceSettings::EMAIL_ALWAYS) {
        $to_phids[] = $phid;
      }
    }
    return $to_phids;
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return array();
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);
    $body->addLinkSection(
      pht('CONPHERENCE DETAIL'),
      PhabricatorEnv::getProductionURI('/conpherence/'.$object->getID().'/'));

    return $body;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.conpherence.subject-prefix');
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function getSearchContextParameter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $comment_phids = array();
    foreach ($xactions as $xaction) {
      if ($xaction->hasComment()) {
        $comment_phids[] = $xaction->getPHID();
      }
    }

    return array(
      'commentPHIDs' => $comment_phids,
    );
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case ConpherenceTransactionType::TYPE_TITLE:
        if (!$object->getIsRoom() && $this->getIsNewObject()) {
          continue;
        }
        $missing = $this->validateIsEmptyTextField(
          $object->getTitle(),
          $xactions);

        if ($missing) {
          if ($object->getIsRoom()) {
            $detail = pht('Room title is required.');
          } else {
            $detail = pht('Thread title can not be blank.');
          }
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            $detail,
            last($xactions));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        foreach ($xactions as $xaction) {
          $phids = $this->getPHIDTransactionNewValue(
            $xaction,
            nonempty($object->getParticipantPHIDs(), array()));

          if (!$phids) {
            continue;
          }

          $users = id(new PhabricatorPeopleQuery())
            ->setViewer($this->requireActor())
            ->withPHIDs($phids)
            ->execute();
          $users = mpull($users, null, 'getPHID');
          foreach ($phids as $phid) {
            if (isset($users[$phid])) {
              continue;
            }
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('New thread member "%s" is not a valid user.', $phid),
              $xaction);
          }
        }
        break;
    }

    return $errors;
  }
}

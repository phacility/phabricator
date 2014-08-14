<?php

final class PhabricatorAuditEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorAuditApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Audits');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;

    // TODO: These will get modernized eventually, but that can happen one
    // at a time later on.
    $types[] = PhabricatorAuditActionConstants::ACTION;
    $types[] = PhabricatorAuditActionConstants::INLINE;
    $types[] = PhabricatorAuditActionConstants::ADD_AUDITORS;

    return $types;
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        return $xaction->hasComment();
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
        return null;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        // TODO: For now, just record the added PHIDs. Eventually, turn these
        // into real edge transactions, probably?
        return array();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
        return;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $new = $xaction->getNewValue();
        if (!is_array($new)) {
          $new = array();
        }

        $old = $xaction->getOldValue();
        if (!is_array($old)) {
          $old = array();
        }

        $add = array_diff_key($new, $old);

        $actor = $this->requireActor();

        $requests = $object->getAudits();
        $requests = mpull($requests, null, 'getAuditorPHID');
        foreach ($add as $phid) {
          if (isset($requests[$phid])) {
            continue;
          }

          $audit_requested = PhabricatorAuditStatusConstants::AUDIT_REQUESTED;
          $requests[] = id (new PhabricatorRepositoryAuditRequest())
            ->setCommitPHID($object->getPHID())
            ->setAuditorPHID($phid)
            ->setAuditStatus($audit_requested)
            ->setAuditReasons(
              array(
                'Added by '.$actor->getUsername(),
              ))
            ->save();
        }

        $object->attachAudits($requests);
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Load auditors explicitly; we may not have them if the caller was a
    // generic piece of infrastructure.

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($this->requireActor())
      ->withIDs(array($object->getID()))
      ->needAuditRequests(true)
      ->executeOne();
    if (!$commit) {
      throw new Exception(
        pht('Failed to load commit during transaction finalization!'));
    }
    $object->attachAudits($commit->getAudits());

    $status_concerned = PhabricatorAuditStatusConstants::CONCERNED;
    $status_closed = PhabricatorAuditStatusConstants::CLOSED;
    $status_resigned = PhabricatorAuditStatusConstants::RESIGNED;
    $status_accepted = PhabricatorAuditStatusConstants::ACCEPTED;
    $status_concerned = PhabricatorAuditStatusConstants::CONCERNED;

    $actor_phid = $this->getActingAsPHID();
    $actor_is_author = ($object->getAuthorPHID()) &&
      ($actor_phid == $object->getAuthorPHID());

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorAuditActionConstants::ACTION:
          $new = $xaction->getNewValue();
          switch ($new) {
            case PhabricatorAuditActionConstants::CLOSE:
              // "Close" means wipe out all the concerns.
              $requests = $object->getAudits();
              foreach ($requests as $request) {
                if ($request->getAuditStatus() == $status_concerned) {
                  $request
                    ->setAuditStatus($status_closed)
                    ->save();
                }
              }
              break;
            case PhabricatorAuditActionConstants::RESIGN:
              $requests = $object->getAudits();
              $requests = mpull($requests, null, 'getAuditorPHID');
              $actor_request = idx($requests, $actor_phid);

              // If the actor doesn't currently have a relationship to the
              // commit, add one explicitly. For example, this allows members
              // of a project to resign from a commit and have it drop out of
              // their queue.

              if (!$actor_request) {
                $actor_request = id(new PhabricatorRepositoryAuditRequest())
                  ->setCommitPHID($object->getPHID())
                  ->setAuditorPHID($actor_phid);

                $requests[] = $actor_request;
                $object->attachAudits($requests);
              }

              $actor_request
                ->setAuditStatus($status_resigned)
                ->save();
              break;
            case PhabricatorAuditActionConstants::ACCEPT:
            case PhabricatorAuditActionConstants::CONCERN:
              if ($new == PhabricatorAuditActionConstants::ACCEPT) {
                $new_status = $status_accepted;
              } else {
                $new_status = $status_concerned;
              }

              $requests = $object->getAudits();
              $requests = mpull($requests, null, 'getAuditorPHID');

              // Figure out which requests the actor has authority over: these
              // are user requests where they are the auditor, and packages
              // and projects they are a member of.

              if ($actor_is_author) {
                // When modifying your own commits, you act only on behalf of
                // yourself, not your packages/projects -- the idea being that
                // you can't accept your own commits.
                $authority_phids = array($actor_phid);
              } else {
                $authority_phids =
                  PhabricatorAuditCommentEditor::loadAuditPHIDsForUser(
                    $this->requireActor());
              }

              $authority = array_select_keys(
                $requests,
                $authority_phids);

              if (!$authority) {
                // If the actor has no authority over any existing requests,
                // create a new request for them.

                $actor_request = id(new PhabricatorRepositoryAuditRequest())
                  ->setCommitPHID($object->getPHID())
                  ->setAuditorPHID($actor_phid)
                  ->setAuditStatus($new_status)
                  ->save();

                $requests[$actor_phid] = $actor_request;
                $object->attachAudits($requests);
              } else {
                // Otherwise, update the audit status of the existing requests.
                foreach ($authority as $request) {
                  $request
                    ->setAuditStatus($new_status)
                    ->save();
                }
              }
              break;

          }
          break;
      }
    }

    $requests = $object->getAudits();
    $object->updateAuditStatus($requests);
    $object->save();

    return $xactions;
  }

  protected function sortTransactions(array $xactions) {
    $xactions = parent::sortTransactions($xactions);

    $head = array();
    $tail = array();

    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PhabricatorAuditActionConstants::INLINE) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    foreach ($xactions as $xaction) {
      switch ($type) {
        case PhabricatorAuditActionConstants::ACTION:
          $error = $this->validateAuditAction(
            $object,
            $type,
            $xaction,
            $xaction->getNewValue());
          if ($error) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $error,
              $xaction);
          }
          break;
      }
    }

    return $errors;
  }

  private function validateAuditAction(
    PhabricatorLiskDAO $object,
    $type,
    PhabricatorAuditTransaction $xaction,
    $action) {

    $can_author_close_key = 'audit.can-author-close-audit';
    $can_author_close = PhabricatorEnv::getEnvConfig($can_author_close_key);

    $actor_is_author = ($object->getAuthorPHID()) &&
      ($object->getAuthorPHID() == $this->getActingAsPHID());

    switch ($action) {
      case PhabricatorAuditActionConstants::CLOSE:
        if (!$actor_is_author) {
          return pht(
            'You can not close this audit because you are not the author '.
            'of the commit.');
        }

        if (!$can_author_close) {
          return pht(
            'You can not close this audit because "%s" is disabled in '.
            'the Phabricator configuration.',
            $can_author_close_key);
        }

        break;
    }

    return null;
  }


  protected function supportsSearch() {
    return true;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $this->isCommitMostlyImported($object);
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    $reply_handler = PhabricatorEnv::newObjectFromConfig(
      'metamta.diffusion.reply-handler');
    $reply_handler->setMailReceiver($object);
    return $reply_handler;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.diffusion.subject-prefix');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    // For backward compatibility, use this legacy thread ID.
    return 'diffusion-audit-'.$object->getPHID();
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $identifier = $object->getCommitIdentifier();
    $repository = $object->getRepository();
    $monogram = $repository->getMonogram();

    $summary = $object->getSummary();
    $name = $repository->formatCommitName($identifier);

    $subject = "{$name}: {$summary}";
    $thread_topic = "Commit {$monogram}{$identifier}";

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->addHeader('Thread-Topic', $thread_topic);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    if ($object->getAuthorPHID()) {
      $phids[] = $object->getAuthorPHID();
    }

    $status_resigned = PhabricatorAuditStatusConstants::RESIGNED;
    foreach ($object->getAudits() as $audit) {
      if ($audit->getAuditStatus() != $status_resigned) {
        $phids[] = $audit->getAuditorPHID();
      }
    }

    return $phids;
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $type_inline = PhabricatorAuditActionConstants::INLINE;

    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_inline) {
        $inlines[] = $xaction;
      }
    }

    if ($inlines) {
      $body->addTextSection(
        pht('INLINE COMMENTS'),
        $this->renderInlineCommentsForMail($object, $inlines));
    }

    // Reload the commit to pull commit data.
    $commit = id(new DiffusionCommitQuery())
      ->setViewer($this->requireActor())
      ->withIDs(array($object->getID()))
      ->needCommitData(true)
      ->executeOne();
    $data = $commit->getCommitData();

    $user_phids = array();

    $author_phid = $commit->getAuthorPHID();
    if ($author_phid) {
      $user_phids[$commit->getAuthorPHID()][] = pht('Author');
    }

    $committer_phid = $data->getCommitDetail('committerPHID');
    if ($committer_phid && ($committer_phid != $author_phid)) {
      $user_phids[$committer_phid][] = pht('Committer');
    }

    // TODO: It would be nice to show pusher here too, but that information
    // is a little tricky to get at right now.

    if ($user_phids) {
      $handle_phids = array_keys($user_phids);
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireActor())
        ->withPHIDs($handle_phids)
        ->execute();

      $user_info = array();
      foreach ($user_phids as $phid => $roles) {
        $user_info[] = pht(
          '%s (%s)',
          $handles[$phid]->getName(),
          implode(', ', $roles));
      }

      $body->addTextSection(
        pht('USERS'),
        implode("\n", $user_info));
    }

    $monogram = $object->getRepository()->formatCommitName(
      $object->getCommitIdentifier());

    $body->addTextSection(
      pht('COMMIT'),
      PhabricatorEnv::getProductionURI('/'.$monogram));

    return $body;
  }

  private function renderInlineCommentsForMail(
    PhabricatorLiskDAO $object,
    array $inline_xactions) {

    $inlines = mpull($inline_xactions, 'getComment');

    $block = array();

    $path_map = id(new DiffusionPathQuery())
      ->withPathIDs(mpull($inlines, 'getPathID'))
      ->execute();
    $path_map = ipull($path_map, 'path', 'id');

    foreach ($inlines as $inline) {
      $path = idx($path_map, $inline->getPathID());
      if ($path === null) {
        continue;
      }

      $start = $inline->getLineNumber();
      $len   = $inline->getLineLength();
      if ($len) {
        $range = $start.'-'.($start + $len);
      } else {
        $range = $start;
      }

      $content = $inline->getContent();
      $block[] = "{$path}:{$range} {$content}";
    }

    return implode("\n", $block);
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return $this->isCommitMostlyImported($object);
  }

  private function isCommitMostlyImported(PhabricatorLiskDAO $object) {
    $has_message = PhabricatorRepositoryCommit::IMPORTED_MESSAGE;
    $has_changes = PhabricatorRepositoryCommit::IMPORTED_CHANGE;

    // Don't publish feed stories or email about events which occur during
    // import. In particular, this affects tasks being attached when they are
    // closed by "Fixes Txxxx" in a commit message. See T5851.

    $mask = ($has_message | $has_changes);

    return $object->isPartiallyImported($mask);
  }

}

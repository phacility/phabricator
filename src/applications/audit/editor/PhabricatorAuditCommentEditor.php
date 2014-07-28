<?php

final class PhabricatorAuditCommentEditor extends PhabricatorEditor {

  private $commit;
  private $attachInlineComments;
  private $noEmail;

  public function __construct(PhabricatorRepositoryCommit $commit) {
    $this->commit = $commit;
    return $this;
  }

  public function setAttachInlineComments($attach_inline_comments) {
    $this->attachInlineComments = $attach_inline_comments;
    return $this;
  }

  public function setNoEmail($no_email) {
    $this->noEmail = $no_email;
    return $this;
  }

  public function addComments(array $comments) {
    assert_instances_of($comments, 'PhabricatorAuditComment');

    $commit = $this->commit;
    $actor = $this->getActor();

    $other_comments = PhabricatorAuditComment::loadComments(
      $actor,
      $commit->getPHID());

    $inline_comments = array();
    if ($this->attachInlineComments) {
      $inline_comments = PhabricatorAuditInlineComment::loadDraftComments(
        $actor,
        $commit->getPHID());
    }

    $content_blocks = array();
    foreach ($comments as $comment) {
      $content_blocks[] = $comment->getContent();
    }

    foreach ($inline_comments as $inline) {
      $content_blocks[] = $inline->getContent();
    }

    // Find any "@mentions" in the content blocks.
    $mention_ccs = PhabricatorMarkupEngine::extractPHIDsFromMentions(
      $this->getActor(),
      $content_blocks);
    if ($mention_ccs) {
      $comments[] = id(new PhabricatorAuditComment())
        ->setAction(PhabricatorAuditActionConstants::ADD_CCS)
        ->setMetadata(
          array(
            PhabricatorAuditComment::METADATA_ADDED_CCS => $mention_ccs,
          ));
    }

    // When an actor submits an audit comment, we update all the audit requests
    // they have authority over to reflect the most recent status. The general
    // idea here is that if audit has triggered for, e.g., several packages, but
    // a user owns all of them, they can clear the audit requirement in one go
    // without auditing the commit for each trigger.

    $audit_phids = self::loadAuditPHIDsForUser($actor);
    $audit_phids = array_fill_keys($audit_phids, true);

    $requests = id(new PhabricatorRepositoryAuditRequest())
      ->loadAllWhere(
        'commitPHID = %s',
        $commit->getPHID());

    $action = $comment->getAction();

    // TODO: We should validate the action, currently we allow anyone to, e.g.,
    // close an audit if they muck with form parameters. I'll followup with this
    // and handle the no-effect cases (e.g., closing and already-closed audit).

    $actor_is_author = ($actor->getPHID() == $commit->getAuthorPHID());

    // Pick a meaningful action, if we have one.
    $action = PhabricatorAuditActionConstants::COMMENT;
    foreach ($comments as $comment) {
      switch ($comment->getAction()) {
        case PhabricatorAuditActionConstants::CLOSE:
        case PhabricatorAuditActionConstants::RESIGN:
        case PhabricatorAuditActionConstants::ACCEPT:
        case PhabricatorAuditActionConstants::CONCERN:
          $action = $comment->getAction();
          break;
      }
    }

    if ($action == PhabricatorAuditActionConstants::CLOSE) {
      if (!PhabricatorEnv::getEnvConfig('audit.can-author-close-audit')) {
        throw new Exception('Cannot Close Audit without enabling'.
          'audit.can-author-close-audit');
      }
      // "Close" means wipe out all the concerns.
      $concerned_status = PhabricatorAuditStatusConstants::CONCERNED;
      foreach ($requests as $request) {
        if ($request->getAuditStatus() == $concerned_status) {
          $request->setAuditStatus(PhabricatorAuditStatusConstants::CLOSED);
          $request->save();
        }
      }
    } else if ($action == PhabricatorAuditActionConstants::RESIGN) {
      // "Resign" has unusual rules for writing user rows, only affects the
      // user row (never package/project rows), and always affects the user
      // row (other actions don't, if they were able to affect a package/project
      // row).
      $actor_request = null;
      foreach ($requests as $request) {
        if ($request->getAuditorPHID() == $actor->getPHID()) {
          $actor_request = $request;
          break;
        }
      }
      if (!$actor_request) {
        $actor_request = id(new PhabricatorRepositoryAuditRequest())
          ->setCommitPHID($commit->getPHID())
          ->setAuditorPHID($actor->getPHID())
          ->setAuditReasons(array('Resigned'));
      }

      $actor_request
        ->setAuditStatus(PhabricatorAuditStatusConstants::RESIGNED)
        ->save();

      $requests[] = $actor_request;
    } else {
      $have_any_requests = false;
      foreach ($requests as $request) {
        if (empty($audit_phids[$request->getAuditorPHID()])) {
          continue;
        }

        $request_is_for_actor =
          ($request->getAuditorPHID() == $actor->getPHID());

        $have_any_requests = true;
        $new_status = null;
        switch ($action) {
          case PhabricatorAuditActionConstants::COMMENT:
          case PhabricatorAuditActionConstants::ADD_CCS:
          case PhabricatorAuditActionConstants::ADD_AUDITORS:
            // Commenting or adding cc's/auditors doesn't change status.
            break;
          case PhabricatorAuditActionConstants::ACCEPT:
            if (!$actor_is_author || $request_is_for_actor) {
              // When modifying your own commits, you act only on behalf of
              // yourself, not your packages/projects -- the idea being that
              // you can't accept your own commits.
              $new_status = PhabricatorAuditStatusConstants::ACCEPTED;
            }
            break;
          case PhabricatorAuditActionConstants::CONCERN:
            if (!$actor_is_author || $request_is_for_actor) {
              // See above.
              $new_status = PhabricatorAuditStatusConstants::CONCERNED;
            }
            break;
          default:
            throw new Exception("Unknown action '{$action}'!");
        }
        if ($new_status !== null) {
          $request->setAuditStatus($new_status);
          $request->save();
        }
      }

      // If the actor has no current authority over any audit trigger, make a
      // new one to represent their audit state.
      if (!$have_any_requests) {
        $new_status = null;
        switch ($action) {
          case PhabricatorAuditActionConstants::COMMENT:
          case PhabricatorAuditActionConstants::ADD_CCS:
          case PhabricatorAuditActionConstants::ADD_AUDITORS:
            $new_status = PhabricatorAuditStatusConstants::AUDIT_NOT_REQUIRED;
            break;
          case PhabricatorAuditActionConstants::ACCEPT:
            $new_status = PhabricatorAuditStatusConstants::ACCEPTED;
            break;
          case PhabricatorAuditActionConstants::CONCERN:
            $new_status = PhabricatorAuditStatusConstants::CONCERNED;
            break;
          case PhabricatorAuditActionConstants::CLOSE:
            // Impossible to reach this block with 'close'.
          default:
            throw new Exception("Unknown or invalid action '{$action}'!");
        }

        $request = id(new PhabricatorRepositoryAuditRequest())
          ->setCommitPHID($commit->getPHID())
          ->setAuditorPHID($actor->getPHID())
          ->setAuditStatus($new_status)
          ->setAuditReasons(array('Voluntary Participant'))
          ->save();
        $requests[] = $request;
      }
    }

    $auditors = array();
    $ccs = array();
    foreach ($comments as $comment) {
      $meta = $comment->getMetadata();

      $auditor_phids = idx(
        $meta,
        PhabricatorAuditComment::METADATA_ADDED_AUDITORS,
        array());
      foreach ($auditor_phids as $phid) {
        $auditors[] = $phid;
      }

      $cc_phids = idx(
        $meta,
        PhabricatorAuditComment::METADATA_ADDED_CCS,
        array());
      foreach ($cc_phids as $phid) {
        $ccs[] = $phid;
      }
    }

    $requests_by_auditor = mpull($requests, null, 'getAuditorPHID');
    $requests_phids = array_keys($requests_by_auditor);

    $ccs = array_diff($ccs, $requests_phids);
    $auditors = array_diff($auditors, $requests_phids);

    if ($auditors) {
      foreach ($auditors as $auditor_phid) {
        $audit_requested = PhabricatorAuditStatusConstants::AUDIT_REQUESTED;
        $requests[] = id (new PhabricatorRepositoryAuditRequest())
          ->setCommitPHID($commit->getPHID())
          ->setAuditorPHID($auditor_phid)
          ->setAuditStatus($audit_requested)
          ->setAuditReasons(
            array('Added by '.$actor->getUsername()))
          ->save();
      }
    }

    if ($ccs) {
      foreach ($ccs as $cc_phid) {
        $audit_cc = PhabricatorAuditStatusConstants::CC;
        $requests[] = id (new PhabricatorRepositoryAuditRequest())
          ->setCommitPHID($commit->getPHID())
          ->setAuditorPHID($cc_phid)
          ->setAuditStatus($audit_cc)
          ->setAuditReasons(
            array('Added by '.$actor->getUsername()))
          ->save();
      }
    }

    $commit->updateAuditStatus($requests);
    $commit->save();

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_LEGACY,
      array());

    foreach ($comments as $comment) {
      $comment
        ->setActorPHID($actor->getPHID())
        ->setTargetPHID($commit->getPHID())
        ->setContentSource($content_source)
        ->save();
    }

    foreach ($inline_comments as $inline) {
      $xaction = id(new PhabricatorAuditComment())
        ->setProxyComment($inline->getTransactionCommentForSave())
        ->setAction(PhabricatorAuditActionConstants::INLINE)
        ->setActorPHID($actor->getPHID())
        ->setTargetPHID($commit->getPHID())
        ->setContentSource($content_source)
        ->save();

      $comments[] = $xaction;
    }

    $feed_dont_publish_phids = array();
    foreach ($requests as $request) {
      $status = $request->getAuditStatus();
      switch ($status) {
      case PhabricatorAuditStatusConstants::RESIGNED:
      case PhabricatorAuditStatusConstants::NONE:
      case PhabricatorAuditStatusConstants::AUDIT_NOT_REQUIRED:
      case PhabricatorAuditStatusConstants::CC:
        $feed_dont_publish_phids[$request->getAuditorPHID()] = 1;
        break;
      default:
        unset($feed_dont_publish_phids[$request->getAuditorPHID()]);
        break;
      }
    }
    $feed_dont_publish_phids = array_keys($feed_dont_publish_phids);

    $feed_phids = array_diff($requests_phids, $feed_dont_publish_phids);
    foreach ($comments as $comment) {
      $this->publishFeedStory($comment, $feed_phids);
    }

    id(new PhabricatorSearchIndexer())
      ->queueDocumentForIndexing($commit->getPHID());

    if (!$this->noEmail) {
      $this->sendMail(
        $comments,
        $other_comments,
        $inline_comments,
        $requests);
    }
  }


  /**
   * Load the PHIDs for all objects the user has the authority to act as an
   * audit for. This includes themselves, and any packages they are an owner
   * of.
   */
  public static function loadAuditPHIDsForUser(PhabricatorUser $user) {
    $phids = array();

    // TODO: This method doesn't really use the right viewer, but in practice we
    // never issue this query of this type on behalf of another user and are
    // unlikely to do so in the future. This entire method should be refactored
    // into a Query class, however, and then we should use a proper viewer.

    // The user can audit on their own behalf.
    $phids[$user->getPHID()] = true;

    $owned_packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($user)
      ->withOwnerPHIDs(array($user->getPHID()))
      ->execute();
    foreach ($owned_packages as $package) {
      $phids[$package->getPHID()] = true;
    }

    // The user can audit on behalf of all projects they are a member of.
    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withMemberPHIDs(array($user->getPHID()))
      ->execute();
    foreach ($projects as $project) {
      $phids[$project->getPHID()] = true;
    }

    return array_keys($phids);
  }

  private function publishFeedStory(
    PhabricatorAuditComment $comment,
    array $more_phids) {

    $commit = $this->commit;
    $actor = $this->getActor();

    $related_phids = array_merge(
      array(
        $actor->getPHID(),
        $commit->getPHID(),
      ),
      $more_phids);

    id(new PhabricatorFeedStoryPublisher())
      ->setRelatedPHIDs($related_phids)
      ->setStoryAuthorPHID($actor->getPHID())
      ->setStoryTime(time())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_AUDIT)
      ->setStoryData(
        array(
          'commitPHID'    => $commit->getPHID(),
          'action'        => $comment->getAction(),
          'content'       => $comment->getContent(),
        ))
      ->publish();
  }

  private function sendMail(
    array $comments,
    array $other_comments,
    array $inline_comments,
    array $requests) {

    assert_instances_of($comments, 'PhabricatorAuditComment');
    assert_instances_of($other_comments, 'PhabricatorAuditComment');
    assert_instances_of($inline_comments, 'PhabricatorInlineCommentInterface');

    $any_comment = head($comments);

    $commit = $this->commit;

    $data = $commit->loadCommitData();
    $summary = $data->getSummary();

    $commit_phid = $commit->getPHID();
    $phids = array($commit_phid);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getActor())
      ->withPHIDs($phids)
      ->execute();
    $handle = $handles[$commit_phid];

    $name = $handle->getName();

    $map = array(
      PhabricatorAuditActionConstants::CONCERN  => 'Raised Concern',
      PhabricatorAuditActionConstants::ACCEPT   => 'Accepted',
      PhabricatorAuditActionConstants::RESIGN   => 'Resigned',
      PhabricatorAuditActionConstants::CLOSE    => 'Closed',
      PhabricatorAuditActionConstants::ADD_CCS => 'Added CCs',
      PhabricatorAuditActionConstants::ADD_AUDITORS => 'Added Auditors',
    );
    $verb = idx($map, $any_comment->getAction(), 'Commented On');

    $reply_handler = self::newReplyHandlerForCommit($commit);

    $prefix = PhabricatorEnv::getEnvConfig('metamta.diffusion.subject-prefix');

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getActor())
      ->withIDs(array($commit->getRepositoryID()))
      ->executeOne();
    $threading = self::getMailThreading($repository, $commit);
    list($thread_id, $thread_topic) = $threading;

    $body = $this->renderMailBody(
      $comments,
      "{$name}: {$summary}",
      $handle,
      $reply_handler,
      $inline_comments);

    $email_to = array();
    $email_cc = array();

    $email_to[$any_comment->getActorPHID()] = true;

    $author_phid = $data->getCommitDetail('authorPHID');
    if ($author_phid) {
      $email_to[$author_phid] = true;
    }

    foreach ($other_comments as $other_comment) {
      $email_cc[$other_comment->getActorPHID()] = true;
    }

    foreach ($requests as $request) {
      switch ($request->getAuditStatus()) {
        case PhabricatorAuditStatusConstants::CC:
        case PhabricatorAuditStatusConstants::AUDIT_REQUIRED:
          $email_cc[$request->getAuditorPHID()] = true;
          break;
        case PhabricatorAuditStatusConstants::RESIGNED:
          unset($email_cc[$request->getAuditorPHID()]);
          break;
        case PhabricatorAuditStatusConstants::CONCERNED:
        case PhabricatorAuditStatusConstants::AUDIT_REQUESTED:
          $email_to[$request->getAuditorPHID()] = true;
          break;
      }
    }

    $email_to = array_keys($email_to);
    $email_cc = array_keys($email_cc);

    $phids = array_merge($email_to, $email_cc);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getActor())
      ->withPHIDs($phids)
      ->execute();

    // NOTE: Always set $is_new to false, because the "first" mail in the
    // thread is the Herald notification of the commit.
    $is_new = false;

    $template = id(new PhabricatorMetaMTAMail())
      ->setSubject("{$name}: {$summary}")
      ->setSubjectPrefix($prefix)
      ->setVarySubjectPrefix("[{$verb}]")
      ->setFrom($any_comment->getActorPHID())
      ->setThreadID($thread_id, $is_new)
      ->addHeader('Thread-Topic', $thread_topic)
      ->setRelatedPHID($commit->getPHID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setIsBulk(true)
      ->setBody($body);

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $email_to),
      array_select_keys($handles, $email_cc));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  public static function getMailThreading(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    return array(
      'diffusion-audit-'.$commit->getPHID(),
      'Commit r'.$repository->getCallsign().$commit->getCommitIdentifier(),
    );
  }

  public static function newReplyHandlerForCommit($commit) {
    $reply_handler = PhabricatorEnv::newObjectFromConfig(
      'metamta.diffusion.reply-handler');
    $reply_handler->setMailReceiver($commit);
    return $reply_handler;
  }

  private function renderMailBody(
    array $comments,
    $cname,
    PhabricatorObjectHandle $handle,
    PhabricatorMailReplyHandler $reply_handler,
    array $inline_comments) {

    assert_instances_of($comments, 'PhabricatorAuditComment');
    assert_instances_of($inline_comments, 'PhabricatorInlineCommentInterface');

    $commit = $this->commit;
    $actor = $this->getActor();
    $name = $actor->getUsername();

    $body = new PhabricatorMetaMTAMailBody();
    foreach ($comments as $comment) {
      if ($comment->getAction() == PhabricatorAuditActionConstants::INLINE) {
        continue;
      }

      $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb(
        $comment->getAction());

      $body->addRawSection("{$name} {$verb} commit {$cname}.");

      $content = $comment->getContent();
      if (strlen($content)) {
        $body->addRawSection($comment->getContent());
      }
    }

    if ($inline_comments) {
      $block = array();

      $path_map = id(new DiffusionPathQuery())
        ->withPathIDs(mpull($inline_comments, 'getPathID'))
        ->execute();
      $path_map = ipull($path_map, 'path', 'id');

      foreach ($inline_comments as $inline) {
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

      $body->addTextSection(pht('INLINE COMMENTS'), implode("\n", $block));
    }

    $body->addTextSection(
      pht('COMMIT'),
      PhabricatorEnv::getProductionURI($handle->getURI()));
    $body->addReplySection($reply_handler->getReplyHandlerInstructions());

    return $body->render();
  }

}

<?php

final class DifferentialCommentEditor extends PhabricatorEditor {

  protected $revision;
  protected $action;

  protected $attachInlineComments;
  protected $message;
  protected $changedByCommit;
  protected $addedReviewers = array();
  protected $removedReviewers = array();
  private $addedCCs = array();

  private $parentMessageID;
  private $contentSource;
  private $noEmail;

  private $isDaemonWorkflow;

  public function __construct(
    DifferentialRevision $revision,
    $action) {

    $this->revision = $revision;
    $this->action   = $action;
  }

  public function setParentMessageID($parent_message_id) {
    $this->parentMessageID = $parent_message_id;
    return $this;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function setAttachInlineComments($attach) {
    $this->attachInlineComments = $attach;
    return $this;
  }

  public function setChangedByCommit($changed_by_commit) {
    $this->changedByCommit = $changed_by_commit;
    return $this;
  }

  public function getChangedByCommit() {
    return $this->changedByCommit;
  }

  public function setAddedReviewers(array $added_reviewers) {
    $this->addedReviewers = $added_reviewers;
    return $this;
  }

  public function getAddedReviewers() {
    return $this->addedReviewers;
  }

  public function setRemovedReviewers(array $removeded_reviewers) {
    $this->removedReviewers = $removeded_reviewers;
    return $this;
  }

  public function getRemovedReviewers() {
    return $this->removedReviewers;
  }

  public function setAddedCCs($added_ccs) {
    $this->addedCCs = $added_ccs;
    return $this;
  }

  public function getAddedCCs() {
    return $this->addedCCs;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function setIsDaemonWorkflow($is_daemon) {
    $this->isDaemonWorkflow = $is_daemon;
    return $this;
  }

  public function setNoEmail($no_email) {
    $this->noEmail = $no_email;
    return $this;
  }

  public function save() {
    $actor              = $this->requireActor();
    $revision           = $this->revision;
    $action             = $this->action;
    $actor_phid         = $actor->getPHID();
    $actor_is_author    = ($actor_phid == $revision->getAuthorPHID());
    $allow_self_accept  = PhabricatorEnv::getEnvConfig(
      'differential.allow-self-accept', false);
    $always_allow_close = PhabricatorEnv::getEnvConfig(
      'differential.always-allow-close', false);
    $revision_status    = $revision->getStatus();

    $revision->loadRelationships();
    $reviewer_phids = $revision->getReviewers();
    if ($reviewer_phids) {
      $reviewer_phids = array_combine($reviewer_phids, $reviewer_phids);
    }

    $metadata = array();

    $inline_comments = array();
    if ($this->attachInlineComments) {
      $inline_comments = id(new DifferentialInlineComment())->loadAllWhere(
        'authorPHID = %s AND revisionID = %d AND commentID IS NULL',
        $actor_phid,
        $revision->getID());
    }

    switch ($action) {
      case DifferentialAction::ACTION_COMMENT:
        if (!$this->message && !$inline_comments) {
          throw new DifferentialActionHasNoEffectException(
            "You are submitting an empty comment with no action: ".
            "you must act on the revision or post a comment.");
        }
        break;

      case DifferentialAction::ACTION_RESIGN:
        if ($actor_is_author) {
          throw new Exception('You can not resign from your own revision!');
        }
        if (empty($reviewer_phids[$actor_phid])) {
          throw new DifferentialActionHasNoEffectException(
            "You can not resign from this revision because you are not ".
            "a reviewer.");
        }
        DifferentialRevisionEditor::alterReviewers(
          $revision,
          $reviewer_phids,
          $rem = array($actor_phid),
          $add = array(),
          $actor_phid);
        break;

      case DifferentialAction::ACTION_ABANDON:
        if (!$actor_is_author) {
          throw new Exception('You can only abandon your own revisions.');
        }

        if ($revision_status == ArcanistDifferentialRevisionStatus::CLOSED) {
          throw new DifferentialActionHasNoEffectException(
            "You can not abandon this revision because it has already ".
            "been closed.");
        }

        if ($revision_status == ArcanistDifferentialRevisionStatus::ABANDONED) {
          throw new DifferentialActionHasNoEffectException(
            "You can not abandon this revision because it has already ".
            "been abandoned.");
        }

        $revision->setStatus(ArcanistDifferentialRevisionStatus::ABANDONED);
        break;

      case DifferentialAction::ACTION_ACCEPT:
        if ($actor_is_author && !$allow_self_accept) {
          throw new Exception('You can not accept your own revision.');
        }
        if (($revision_status !=
             ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) &&
            ($revision_status !=
             ArcanistDifferentialRevisionStatus::NEEDS_REVISION)) {

          switch ($revision_status) {
            case ArcanistDifferentialRevisionStatus::ACCEPTED:
              throw new DifferentialActionHasNoEffectException(
                "You can not accept this revision because someone else ".
                "already accepted it.");
            case ArcanistDifferentialRevisionStatus::ABANDONED:
              throw new DifferentialActionHasNoEffectException(
                "You can not accept this revision because it has been ".
                "abandoned.");
            case ArcanistDifferentialRevisionStatus::CLOSED:
              throw new DifferentialActionHasNoEffectException(
                "You can not accept this revision because it has already ".
                "been closed.");
            default:
              throw new Exception(
                "Unexpected revision state '{$revision_status}'!");
          }
        }

        $revision
          ->setStatus(ArcanistDifferentialRevisionStatus::ACCEPTED);

        if (!isset($reviewer_phids[$actor_phid])) {
          DifferentialRevisionEditor::alterReviewers(
            $revision,
            $reviewer_phids,
            $rem = array(),
            $add = array($actor_phid),
            $actor_phid);
        }
        break;

      case DifferentialAction::ACTION_REQUEST:
        if (!$actor_is_author) {
          throw new Exception('You must own a revision to request review.');
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
            $revision->setStatus(
              ArcanistDifferentialRevisionStatus::NEEDS_REVIEW);
            break;
          case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
            throw new DifferentialActionHasNoEffectException(
              "You can not request review of this revision because it has ".
              "been abandoned.");
          case ArcanistDifferentialRevisionStatus::ABANDONED:
            throw new DifferentialActionHasNoEffectException(
              "You can not request review of this revision because it has ".
              "been abandoned.");
          case ArcanistDifferentialRevisionStatus::CLOSED:
            throw new DifferentialActionHasNoEffectException(
              "You can not request review of this revision because it has ".
              "already been closed.");
          default:
            throw new Exception(
              "Unexpected revision state '{$revision_status}'!");
        }

        list($added_reviewers, $ignored) = $this->alterReviewers();
        if ($added_reviewers) {
          $key = DifferentialComment::METADATA_ADDED_REVIEWERS;
          $metadata[$key] = $added_reviewers;
        }

        break;

      case DifferentialAction::ACTION_REJECT:
        if ($actor_is_author) {
          throw new Exception(
            'You can not request changes to your own revision.');
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
            // NOTE: We allow you to reject an already-rejected revision
            // because it doesn't create any ambiguity and avoids a rather
            // needless dialog.
            break;
          case ArcanistDifferentialRevisionStatus::ABANDONED:
            throw new DifferentialActionHasNoEffectException(
              "You can not request changes to this revision because it has ".
              "been abandoned.");
          case ArcanistDifferentialRevisionStatus::CLOSED:
            throw new DifferentialActionHasNoEffectException(
              "You can not request changes to this revision because it has ".
              "already been closed.");
          default:
            throw new Exception(
              "Unexpected revision state '{$revision_status}'!");
        }

        if (!isset($reviewer_phids[$actor_phid])) {
          DifferentialRevisionEditor::alterReviewers(
            $revision,
            $reviewer_phids,
            $rem = array(),
            $add = array($actor_phid),
            $actor_phid);
        }

        $revision
          ->setStatus(ArcanistDifferentialRevisionStatus::NEEDS_REVISION);
        break;

      case DifferentialAction::ACTION_RETHINK:
        if (!$actor_is_author) {
          throw new Exception(
            "You can not plan changes to somebody else's revision");
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
            break;
          case ArcanistDifferentialRevisionStatus::ABANDONED:
            throw new DifferentialActionHasNoEffectException(
              "You can not plan changes to this revision because it has ".
              "been abandoned.");
          case ArcanistDifferentialRevisionStatus::CLOSED:
            throw new DifferentialActionHasNoEffectException(
              "You can not plan changes to this revision because it has ".
              "already been closed.");
          default:
            throw new Exception(
              "Unexpected revision state '{$revision_status}'!");
        }

        $revision
          ->setStatus(ArcanistDifferentialRevisionStatus::NEEDS_REVISION);
        break;

      case DifferentialAction::ACTION_RECLAIM:
        if (!$actor_is_author) {
          throw new Exception('You can not reclaim a revision you do not own.');
        }


        if ($revision_status != ArcanistDifferentialRevisionStatus::ABANDONED) {
          throw new DifferentialActionHasNoEffectException(
            "You can not reclaim this revision because it is not abandoned.");
        }

        $revision
          ->setStatus(ArcanistDifferentialRevisionStatus::NEEDS_REVIEW);
        break;

      case DifferentialAction::ACTION_CLOSE:

        // NOTE: The daemons can mark things closed from any state. We treat
        // them as completely authoritative.

        if (!$this->isDaemonWorkflow) {
          if (!$actor_is_author && !$always_allow_close) {
            throw new Exception(
              "You can not mark a revision you don't own as closed.");
          }

          $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;
          $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;

          if ($revision_status == $status_closed) {
            throw new DifferentialActionHasNoEffectException(
              "You can not mark this revision as closed because it has ".
              "already been marked as closed.");
          }

          if ($revision_status != $status_accepted) {
            throw new DifferentialActionHasNoEffectException(
              "You can not mark this revision as closed because it is ".
              "has not been accepted.");
          }
        }

        if (!$revision->getDateCommitted()) {
          $revision->setDateCommitted(time());
        }

        $revision->setStatus(ArcanistDifferentialRevisionStatus::CLOSED);
        break;

      case DifferentialAction::ACTION_ADDREVIEWERS:
        list($added_reviewers, $ignored) = $this->alterReviewers();

        if ($added_reviewers) {
          $key = DifferentialComment::METADATA_ADDED_REVIEWERS;
          $metadata[$key] = $added_reviewers;
        } else {
          $user_tried_to_add = count($this->getAddedReviewers());
          if ($user_tried_to_add == 0) {
            throw new DifferentialActionHasNoEffectException(
              "You can not add reviewers, because you did not specify any ".
              "reviewers.");
          } else if ($user_tried_to_add == 1) {
            throw new DifferentialActionHasNoEffectException(
              "You can not add that reviewer, because they are already an ".
              "author or reviewer.");
          } else {
            throw new DifferentialActionHasNoEffectException(
              "You can not add those reviewers, because they are all already ".
              "authors or reviewers.");
          }
        }

        break;
      case DifferentialAction::ACTION_ADDCCS:
        $added_ccs = $this->getAddedCCs();
        $user_tried_to_add = count($added_ccs);

        $added_ccs = $this->filterAddedCCs($added_ccs);

        if ($added_ccs) {
          foreach ($added_ccs as $cc) {
            DifferentialRevisionEditor::addCC(
              $revision,
              $cc,
              $actor_phid);
          }

          $key = DifferentialComment::METADATA_ADDED_CCS;
          $metadata[$key] = $added_ccs;

        } else {
          if ($user_tried_to_add == 0) {
            throw new DifferentialActionHasNoEffectException(
              "You can not add CCs, because you did not specify any ".
              "CCs.");
          } else if ($user_tried_to_add == 1) {
            throw new DifferentialActionHasNoEffectException(
              "You can not add that CC, because they are already an ".
              "author, reviewer or CC.");
          } else {
            throw new DifferentialActionHasNoEffectException(
              "You can not add those CCs, because they are all already ".
              "authors, reviewers or CCs.");
          }
        }
        break;
      case DifferentialAction::ACTION_CLAIM:
        if ($actor_is_author) {
          throw new Exception("You can not commandeer your own revision.");
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::CLOSED:
            throw new DifferentialActionHasNoEffectException(
              "You can not commandeer this revision because it has ".
              "already been closed.");
            break;
        }

        $this->setAddedReviewers(array($revision->getAuthorPHID()));
        $this->setRemovedReviewers(array($actor_phid));

        // NOTE: Set the new author PHID before calling addReviewers(), since it
        // doesn't permit the author to become a reviewer.
        $revision->setAuthorPHID($actor_phid);

        list($added_reviewers, $removed_reviewers) = $this->alterReviewers();
        if ($added_reviewers) {
          $key = DifferentialComment::METADATA_ADDED_REVIEWERS;
          $metadata[$key] = $added_reviewers;
        }

        if ($removed_reviewers) {
          $key = DifferentialComment::METADATA_REMOVED_REVIEWERS;
          $metadata[$key] = $removed_reviewers;
        }

        break;
      default:
        throw new Exception('Unsupported action.');
    }

    // Update information about reviewer in charge.
    if ($action == DifferentialAction::ACTION_ACCEPT ||
        $action == DifferentialAction::ACTION_REJECT) {
      $revision->setLastReviewerPHID($actor_phid);
    }

    // TODO: Call beginReadLocking() prior to loading the revision.
    $revision->openTransaction();

    // Always save the revision (even if we didn't actually change any of its
    // properties) so that it jumps to the top of the revision list when sorted
    // by "updated". Notably, this allows "ping" comments to push it to the
    // top of the action list.
    $revision->save();

    if ($action != DifferentialAction::ACTION_RESIGN) {
      DifferentialRevisionEditor::addCC(
        $revision,
        $actor_phid,
        $actor_phid);
    }

    $comment = id(new DifferentialComment())
      ->setAuthorPHID($actor_phid)
      ->setRevisionID($revision->getID())
      ->setAction($action)
      ->setContent((string)$this->message)
      ->setMetadata($metadata);

    if ($this->contentSource) {
      $comment->setContentSource($this->contentSource);
    }

    $comment->save();

    $changesets = array();
    if ($inline_comments) {
      $load_ids = mpull($inline_comments, 'getChangesetID');
      if ($load_ids) {
        $load_ids = array_unique($load_ids);
        $changesets = id(new DifferentialChangeset())->loadAllWhere(
          'id in (%Ld)',
          $load_ids);
      }
      foreach ($inline_comments as $inline) {
        $inline->setCommentID($comment->getID());
        $inline->save();
      }
    }

    // Find any "@mentions" in the comment blocks.
    $content_blocks = array($comment->getContent());
    foreach ($inline_comments as $inline) {
      $content_blocks[] = $inline->getContent();
    }
    $mention_ccs = PhabricatorMarkupEngine::extractPHIDsFromMentions(
      $content_blocks);
    if ($mention_ccs) {
      $mention_ccs = $this->filterAddedCCs($mention_ccs);
      if ($mention_ccs) {
        $metadata = $comment->getMetadata();
        $metacc = idx(
          $metadata,
          DifferentialComment::METADATA_ADDED_CCS,
          array());
        foreach ($mention_ccs as $cc_phid) {
          DifferentialRevisionEditor::addCC(
            $revision,
            $cc_phid,
            $actor_phid);
          $metacc[] = $cc_phid;
        }
        $metadata[DifferentialComment::METADATA_ADDED_CCS] = $metacc;

        $comment->setMetadata($metadata);
        $comment->save();
      }
    }

    $revision->saveTransaction();

    $phids = array($actor_phid);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();
    $actor_handle = $handles[$actor_phid];

    $xherald_header = HeraldTranscript::loadXHeraldRulesHeader(
      $revision->getPHID());

    $mailed_phids = array();
    if (!$this->noEmail) {
      $mail = id(new DifferentialCommentMail(
        $revision,
        $actor_handle,
        $comment,
        $changesets,
        $inline_comments))
        ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
        ->setToPHIDs(
          array_merge(
            $revision->getReviewers(),
            array($revision->getAuthorPHID())))
        ->setCCPHIDs($revision->getCCPHIDs())
        ->setChangedByCommit($this->getChangedByCommit())
        ->setXHeraldRulesHeader($xherald_header)
        ->setParentMessageID($this->parentMessageID)
        ->send();

      $mailed_phids = $mail->getRawMail()->buildRecipientList();
    }

    $event_data = array(
      'revision_id'          => $revision->getID(),
      'revision_phid'        => $revision->getPHID(),
      'revision_name'        => $revision->getTitle(),
      'revision_author_phid' => $revision->getAuthorPHID(),
      'action'               => $comment->getAction(),
      'feedback_content'     => $comment->getContent(),
      'actor_phid'           => $actor_phid,
    );

    // TODO: Get rid of this
    id(new PhabricatorTimelineEvent('difx', $event_data))
      ->recordEvent();

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType('PhabricatorFeedStoryDifferential')
      ->setStoryData($event_data)
      ->setStoryTime(time())
      ->setStoryAuthorPHID($actor_phid)
      ->setRelatedPHIDs(
        array(
          $revision->getPHID(),
          $actor_phid,
          $revision->getAuthorPHID(),
        ))
      ->setPrimaryObjectPHID($revision->getPHID())
      ->setSubscribedPHIDs(
        array_merge(
          array($revision->getAuthorPHID()),
          $revision->getReviewers(),
          $revision->getCCPHIDs()))
      ->setMailRecipientPHIDs($mailed_phids)
      ->publish();

    // TODO: Move to workers
    PhabricatorSearchDifferentialIndexer::indexRevision($revision);

    return $comment;
  }

  private function filterAddedCCs(array $ccs) {
    $revision = $this->revision;

    $current_ccs = $revision->getCCPHIDs();
    $current_ccs = array_fill_keys($current_ccs, true);

    $reviewer_phids = $revision->getReviewers();
    $reviewer_phids = array_fill_keys($reviewer_phids, true);

    foreach ($ccs as $key => $cc) {
      if (isset($current_ccs[$cc])) {
        unset($ccs[$key]);
      }
      if (isset($reviewer_phids[$cc])) {
        unset($ccs[$key]);
      }
      if ($cc == $revision->getAuthorPHID()) {
        unset($ccs[$key]);
      }
    }

    return $ccs;
  }

  private function alterReviewers() {
    $actor_phid        = $this->getActor()->getPHID();
    $revision          = $this->revision;
    $added_reviewers   = $this->getAddedReviewers();
    $removed_reviewers = $this->getRemovedReviewers();
    $reviewer_phids    = $revision->getReviewers();
    $allow_self_accept = PhabricatorEnv::getEnvConfig(
      'differential.allow-self-accept', false);

    $reviewer_phids_map = array_fill_keys($reviewer_phids, true);
    foreach ($added_reviewers as $k => $user_phid) {
      if (!$allow_self_accept && $user_phid == $revision->getAuthorPHID()) {
        unset($added_reviewers[$k]);
      }
      if (isset($reviewer_phids_map[$user_phid])) {
        unset($added_reviewers[$k]);
      }
    }

    foreach ($removed_reviewers as $k => $user_phid) {
      if (!isset($reviewer_phids_map[$user_phid])) {
        unset($removed_reviewers[$k]);
      }
    }

    $added_reviewers = array_unique($added_reviewers);
    $removed_reviewers = array_unique($removed_reviewers);

    if ($added_reviewers) {
      DifferentialRevisionEditor::alterReviewers(
        $revision,
        $reviewer_phids,
        $removed_reviewers,
        $added_reviewers,
        $actor_phid);
    }

    return array($added_reviewers, $removed_reviewers);
  }

}

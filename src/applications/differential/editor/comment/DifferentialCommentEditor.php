<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class DifferentialCommentEditor {

  protected $revision;
  protected $actorPHID;
  protected $action;

  protected $attachInlineComments;
  protected $message;
  protected $changedByCommit;
  protected $addedReviewers = array();
  private $addedCCs = array();

  private $parentMessageID;
  private $contentSource;

  private $isDaemonWorkflow;

  public function __construct(
    DifferentialRevision $revision,
    $actor_phid,
    $action) {

    $this->revision = $revision;
    $this->actorPHID  = $actor_phid;
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

  public function save() {
    $revision = $this->revision;
    $action = $this->action;
    $actor_phid = $this->actorPHID;
    $actor = id(new PhabricatorUser())->loadOneWhere('PHID = %s', $actor_phid);
    $actor_is_author = ($actor_phid == $revision->getAuthorPHID());
    $revision_status = $revision->getStatus();

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
        $this->actorPHID,
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

        if ($revision_status == ArcanistDifferentialRevisionStatus::COMMITTED) {
          throw new DifferentialActionHasNoEffectException(
            "You can not abandon this revision because it has already ".
            "been committed.");
        }

        if ($revision_status == ArcanistDifferentialRevisionStatus::ABANDONED) {
          throw new DifferentialActionHasNoEffectException(
            "You can not abandon this revision because it has already ".
            "been abandoned.");
        }

        $revision->setStatus(ArcanistDifferentialRevisionStatus::ABANDONED);
        break;

      case DifferentialAction::ACTION_ACCEPT:
        if ($actor_is_author) {
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
            case ArcanistDifferentialRevisionStatus::COMMITTED:
              throw new DifferentialActionHasNoEffectException(
                "You can not accept this revision because it has already ".
                "been committed.");
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
          case ArcanistDifferentialRevisionStatus::COMMITTED:
            throw new DifferentialActionHasNoEffectException(
              "You can not request review of this revision because it has ".
              "already been committed.");
          default:
            throw new Exception(
              "Unexpected revision state '{$revision_status}'!");
        }

        $added_reviewers = $this->addReviewers();
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
          case ArcanistDifferentialRevisionStatus::COMMITTED:
            throw new DifferentialActionHasNoEffectException(
              "You can not request changes to this revision because it has ".
              "already been committed.");
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
          case ArcanistDifferentialRevisionStatus::COMMITTED:
            throw new DifferentialActionHasNoEffectException(
              "You can not plan changes to this revision because it has ".
              "already been committed.");
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

      case DifferentialAction::ACTION_COMMIT:

        // NOTE: The daemons can mark things committed from any state. We treat
        // them as completely authoritative.

        if (!$this->isDaemonWorkflow) {
          if (!$actor_is_author) {
            throw new Exception(
              "You can not mark a revision you don't own as committed.");
          }

          $status_committed = ArcanistDifferentialRevisionStatus::COMMITTED;
          $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;

          if ($revision_status == $status_committed) {
            throw new DifferentialActionHasNoEffectException(
              "You can not mark this revision as committed because it has ".
              "already been marked as committed.");
          }

          if ($revision_status != $status_accepted) {
            throw new DifferentialActionHasNoEffectException(
              "You can not mark this revision as committed because it has ".
              "not been accepted.");
          }
        }

        if (!$revision->getDateCommitted()) {
          $revision->setDateCommitted(time());
        }

        $revision
          ->setStatus(ArcanistDifferentialRevisionStatus::COMMITTED);
        break;

      case DifferentialAction::ACTION_ADDREVIEWERS:
        $added_reviewers = $this->addReviewers();

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
              $this->actorPHID);
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
          case ArcanistDifferentialRevisionStatus::COMMITTED:
            throw new DifferentialActionHasNoEffectException(
              "You can not commandeer this revision because it has ".
              "already been committed.");
            break;
        }

        $this->setAddedReviewers(array($revision->getAuthorPHID()));

        // NOTE: Set the new author PHID before calling addReviewers(), since it
        // doesn't permit the author to become a reviewer.
        $revision->setAuthorPHID($actor_phid);

        $added_reviewers = $this->addReviewers();
        if ($added_reviewers) {
          $key = DifferentialComment::METADATA_ADDED_REVIEWERS;
          $metadata[$key] = $added_reviewers;
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

    // Always save the revision (even if we didn't actually change any of its
    // properties) so that it jumps to the top of the revision list when sorted
    // by "updated". Notably, this allows "ping" comments to push it to the
    // top of the action list.
    $revision->save();

    if ($action != DifferentialAction::ACTION_RESIGN) {
      DifferentialRevisionEditor::addCC(
        $revision,
        $this->actorPHID,
        $this->actorPHID);
    }

    $comment = id(new DifferentialComment())
      ->setAuthorPHID($this->actorPHID)
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
            $this->actorPHID);
          $metacc[] = $cc_phid;
        }
        $metadata[DifferentialComment::METADATA_ADDED_CCS] = $metacc;

        $comment->setMetadata($metadata);
        $comment->save();
      }
    }

    $phids = array($this->actorPHID);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();
    $actor_handle = $handles[$this->actorPHID];

    $xherald_header = HeraldTranscript::loadXHeraldRulesHeader(
      $revision->getPHID());

    id(new DifferentialCommentMail(
      $revision,
      $actor_handle,
      $comment,
      $changesets,
      $inline_comments))
      ->setToPHIDs(
        array_merge(
          $revision->getReviewers(),
          array($revision->getAuthorPHID())))
      ->setCCPHIDs($revision->getCCPHIDs())
      ->setChangedByCommit($this->getChangedByCommit())
      ->setXHeraldRulesHeader($xherald_header)
      ->setParentMessageID($this->parentMessageID)
      ->send();

    $event_data = array(
      'revision_id'          => $revision->getID(),
      'revision_phid'        => $revision->getPHID(),
      'revision_name'        => $revision->getTitle(),
      'revision_author_phid' => $revision->getAuthorPHID(),
      'action'               => $comment->getAction(),
      'feedback_content'     => $comment->getContent(),
      'actor_phid'           => $this->actorPHID,
    );
    id(new PhabricatorTimelineEvent('difx', $event_data))
      ->recordEvent();

    // TODO: Move to a daemon?
    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_DIFFERENTIAL)
      ->setStoryData($event_data)
      ->setStoryTime(time())
      ->setStoryAuthorPHID($this->actorPHID)
      ->setRelatedPHIDs(
        array(
          $revision->getPHID(),
          $this->actorPHID,
          $revision->getAuthorPHID(),
        ))
      ->publish();

    // TODO: Move to a daemon?
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

  private function addReviewers() {
    $revision = $this->revision;
    $added_reviewers = $this->getAddedReviewers();
    $reviewer_phids = $revision->getReviewers();

    foreach ($added_reviewers as $k => $user_phid) {
      if ($user_phid == $revision->getAuthorPHID()) {
        unset($added_reviewers[$k]);
      }
      if (!empty($reviewer_phids[$user_phid])) {
        unset($added_reviewers[$k]);
      }
    }

    $added_reviewers = array_unique($added_reviewers);

    if ($added_reviewers) {
      DifferentialRevisionEditor::alterReviewers(
        $revision,
        $reviewer_phids,
        $rem = array(),
        $added_reviewers,
        $this->actorPHID);
    }

    return $added_reviewers;
  }

}

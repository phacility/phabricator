<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class DifferentialCommentEditor {

  protected $revision;
  protected $actorPHID;
  protected $action;

  protected $attachInlineComments;
  protected $message;
  protected $addCC;
  protected $changedByCommit;
  protected $addedReviewers = array();
  private $addedCCs = array();

  private $parentMessageID;
  private $contentSource;

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

  public function setAddCC($add) {
    $this->addCC = $add;
    return $this;
  }

  public function setChangedByCommit($changed_by_commit) {
    $this->changedByCommit = $changed_by_commit;
    return $this;
  }

  public function getChangedByCommit() {
    return $this->changedByCommit;
  }

  public function setAddedReviewers($added_reviewers) {
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

  public function save() {
    $revision = $this->revision;
    $action = $this->action;
    $actor_phid = $this->actorPHID;
    $actor = id(new PhabricatorUser())->loadOneWhere('PHID = %s', $actor_phid);
    $actor_is_author = ($actor_phid == $revision->getAuthorPHID());
    $actor_is_admin = $actor->getIsAdmin();
    $revision_status = $revision->getStatus();

    $revision->loadRelationships();
    $reviewer_phids = $revision->getReviewers();
    if ($reviewer_phids) {
      $reviewer_phids = array_combine($reviewer_phids, $reviewer_phids);
    }

    $metadata = array();

    switch ($action) {
      case DifferentialAction::ACTION_COMMENT:
        break;

      case DifferentialAction::ACTION_RESIGN:
        if ($actor_is_author) {
          throw new Exception('You can not resign from your own revision!');
        }
        if (isset($reviewer_phids[$actor_phid])) {
          DifferentialRevisionEditor::alterReviewers(
            $revision,
            $reviewer_phids,
            $rem = array($actor_phid),
            $add = array(),
            $actor_phid);
        }
        break;

      case DifferentialAction::ACTION_ABANDON:
        if (!($actor_is_author || $actor_is_admin)) {
          throw new Exception('You can only abandon your revisions.');
        }
        if ($revision_status == DifferentialRevisionStatus::COMMITTED) {
          throw new Exception('You can not abandon a committed revision.');
        }
        if ($revision_status == DifferentialRevisionStatus::ABANDONED) {
          $action = DifferentialAction::ACTION_COMMENT;
          break;
        }

        $revision
          ->setStatus(DifferentialRevisionStatus::ABANDONED)
          ->save();
        break;

      case DifferentialAction::ACTION_ACCEPT:
        if ($actor_is_author) {
          throw new Exception('You can not accept your own revision.');
        }
        if (($revision_status != DifferentialRevisionStatus::NEEDS_REVIEW) &&
            ($revision_status != DifferentialRevisionStatus::NEEDS_REVISION)) {
          $action = DifferentialAction::ACTION_COMMENT;
          break;
        }

        $revision
          ->setStatus(DifferentialRevisionStatus::ACCEPTED)
          ->save();

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
        if (($revision_status != DifferentialRevisionStatus::NEEDS_REVISION) &&
            ($revision_status != DifferentialRevisionStatus::ACCEPTED)) {
          $action = DifferentialAction::ACTION_COMMENT;
          break;
        }

        $revision
          ->setStatus(DifferentialRevisionStatus::NEEDS_REVIEW)
          ->save();
        break;

      case DifferentialAction::ACTION_REJECT:
        if ($actor_is_author) {
          throw new Exception(
            'You can not request changes to your own revision.');
        }
        if (($revision_status != DifferentialRevisionStatus::NEEDS_REVIEW) &&
            ($revision_status != DifferentialRevisionStatus::ACCEPTED)) {
          $action = DifferentialAction::ACTION_COMMENT;
          break;
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
          ->setStatus(DifferentialRevisionStatus::NEEDS_REVISION)
          ->save();
        break;

      case DifferentialAction::ACTION_RETHINK:
        if (!$actor_is_author) {
          throw new Exception(
            "You can not plan changes to somebody else's revision");
        }
        if (($revision_status != DifferentialRevisionStatus::NEEDS_REVIEW) &&
            ($revision_status != DifferentialRevisionStatus::ACCEPTED)) {
          $action = DifferentialAction::ACTION_COMMENT;
          break;
        }

        $revision
          ->setStatus(DifferentialRevisionStatus::NEEDS_REVISION)
          ->save();
        break;

      case DifferentialAction::ACTION_RECLAIM:
        if (!$actor_is_author) {
          throw new Exception('You can not reclaim a revision you do not own.');
        }
        if ($revision_status != DifferentialRevisionStatus::ABANDONED) {
          $action = DifferentialAction::ACTION_COMMENT;
          break;
        }
        $revision
          ->setStatus(DifferentialRevisionStatus::NEEDS_REVIEW)
          ->save();
        break;

      case DifferentialAction::ACTION_COMMIT:
        if (!$actor_is_author) {
          throw new Exception('You can not commit a revision you do not own.');
        }
        $revision
          ->setStatus(DifferentialRevisionStatus::COMMITTED)
          ->save();
        break;

      case DifferentialAction::ACTION_ADDREVIEWERS:
        $added_reviewers = $this->getAddedReviewers();
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
            $add = $added_reviewers,
            $actor_phid);

          $key = DifferentialComment::METADATA_ADDED_REVIEWERS;
          $metadata[$key] = $added_reviewers;

        } else {
          $action = DifferentialAction::ACTION_COMMENT;
        }
        break;
      case DifferentialAction::ACTION_ADDCCS:
        $added_ccs = $this->getAddedCCs();

        $current_ccs = $revision->getCCPHIDs();
        if ($current_ccs) {
          $current_ccs = array_fill_keys($current_ccs, true);
          foreach ($added_ccs as $k => $cc) {
            if (isset($current_ccs[$cc])) {
              unset($added_ccs[$k]);
            }
          }
        }

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
          $action = DifferentialAction::ACTION_COMMENT;
        }
        break;
      default:
        throw new Exception('Unsupported action.');
    }

    if ($this->addCC) {
      DifferentialRevisionEditor::addCC(
        $revision,
        $this->actorPHID,
        $this->actorPHID);
    }

    $inline_comments = array();
    if ($this->attachInlineComments) {
      $inline_comments = id(new DifferentialInlineComment())->loadAllWhere(
        'authorPHID = %s AND revisionID = %d AND commentID IS NULL',
        $this->actorPHID,
        $revision->getID());
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
      $current_ccs = $revision->getCCPHIDs();
      if ($current_ccs) {
        $current_ccs = array_fill_keys($current_ccs, true);
        foreach ($mention_ccs as $key => $mention_cc) {
          if (isset($current_ccs[$mention_cc])) {
            unset($mention_ccs[$key]);
          }
        }
      }
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

}

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

  public function __construct(
    DifferentialRevision $revision,
    $actor_phid,
    $action) {

    $this->revision = $revision;
    $this->actorPHID  = $actor_phid;
    $this->action   = $action;
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

  public function save() {
    $revision = $this->revision;
    $action = $this->action;
    $actor_phid = $this->actorPHID;
    $actor_is_author = ($actor_phid == $revision->getAuthorPHID());
    $revision_status = $revision->getStatus();

    $revision->loadRelationships();
    $reviewer_phids = $revision->getReviewers();
    if ($reviewer_phids) {
      $reviewer_phids = array_combine($reviewer_phids, $reviewer_phids);
    }

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
        if (!$actor_is_author) {
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

          $handles = id(new PhabricatorObjectHandleData($added_reviewers))
            ->loadHandles();
          $usernames = mpull($handles, 'getName');

          $this->message =
            'Added reviewers: '.implode(', ', $usernames)."\n\n".
            $this->message;

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

    // Reload relationships to pick up any reviewer/CC changes.
    $revision->loadRelationships();

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
      ->save();

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

    $phids = array($this->actorPHID);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();
    $actor_handle = $handles[$this->actorPHID];

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
      ->send();

/*

  TODO

    $event = array(
      'revision_id' => $revision->getID(),
      'fbid'        => $revision->getFBID(),
      'feedback_id' => $feedback->getID(),
      'action'      => $feedback->getAction(),
      'actor'       => $this->actorPHID,
    );
    id(new ToolsTimelineEvent('difx', fb_json_encode($event)))->record();
*/

    return $comment;
  }

}

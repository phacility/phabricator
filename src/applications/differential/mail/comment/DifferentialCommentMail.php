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

class DifferentialCommentMail extends DifferentialMail {

  protected $changedByCommit;

  public function setChangedByCommit($changed_by_commit) {
    $this->changedByCommit = $changed_by_commit;
    return $this;
  }

  public function getChangedByCommit() {
    return $this->changedByCommit;
  }

  public function __construct(
    DifferentialRevision $revision,
    PhabricatorObjectHandle $actor,
    DifferentialComment $comment,
    array $changesets,
    array $inline_comments) {

    $this->setRevision($revision);
    $this->setActorHandle($actor);
    $this->setComment($comment);
    $this->setChangesets($changesets);
    $this->setInlineComments($inline_comments);

  }

  protected function getMailTags() {
    $comment = $this->getComment();
    $action = $comment->getAction();

    $tags = array();
    switch ($action) {
      case DifferentialAction::ACTION_ADDCCS:
        $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_CC;
        break;
      case DifferentialAction::ACTION_COMMIT:
        $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_COMMITTED;
        break;
    }

    if (strlen(trim($comment->getContent()))) {
      switch ($action) {
        case DifferentialAction::ACTION_COMMIT:
          // Commit comments are auto-generated and not especially interesting,
          // so don't tag them as having a comment.
          break;
        default:
          $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_COMMENT;
          break;
      }
    }

    return $tags;
  }

  protected function renderSubject() {
    $verb = ucwords($this->getVerb());
    $revision = $this->getRevision();
    $title = $revision->getTitle();
    $id = $revision->getID();
    $subject = "[{$verb}] D{$id}: {$title}";
    return $subject;
  }

  protected function getVerb() {
    $comment = $this->getComment();
    $action = $comment->getAction();
    $verb = DifferentialAction::getActionPastTenseVerb($action);
    return $verb;
  }

  protected function renderBody() {

    $comment = $this->getComment();

    $actor = $this->getActorName();
    $name  = $this->getRevision()->getTitle();
    $verb  = $this->getVerb();

    $body  = array();

    $body[] = "{$actor} has {$verb} the revision \"{$name}\".";

    // If the commented added reviewers or CCs, list them explicitly.
    $meta = $comment->getMetadata();
    $m_reviewers = idx(
      $meta,
      DifferentialComment::METADATA_ADDED_REVIEWERS,
      array());
    $m_cc = idx(
      $meta,
      DifferentialComment::METADATA_ADDED_CCS,
      array());
    $load = array_merge($m_reviewers, $m_cc);
    if ($load) {
      $handles = id(new PhabricatorObjectHandleData($load))->loadHandles();
      if ($m_reviewers) {
        $body[] = 'Added Reviewers: '.$this->renderHandleList(
          $handles,
          $m_reviewers);
      }
      if ($m_cc) {
        $body[] = 'Added CCs: '.$this->renderHandleList(
          $handles,
          $m_cc);
      }
    }

    $body[] = null;

    $content = $comment->getContent();
    if (strlen($content)) {
      $body[] = $this->formatText($content);
      $body[] = null;
    }

    if ($this->getChangedByCommit()) {
      $body[] = 'CHANGED PRIOR TO COMMIT';
      $body[] = '  This revision was updated prior to commit.';
      $body[] = null;
    }

    $inlines = $this->getInlineComments();
    if ($inlines) {
      $body[] = 'INLINE COMMENTS';
      $changesets = $this->getChangesets();
      foreach ($inlines as $inline) {
        $changeset = $changesets[$inline->getChangesetID()];
        if (!$changeset) {
          throw new Exception('Changeset missing!');
        }
        $file = $changeset->getFilename();
        $start = $inline->getLineNumber();
        $len = $inline->getLineLength();
        if ($len) {
          $range = $start.'-'.($start + $len);
        } else {
          $range = $start;
        }
        $content = $inline->getContent();
        $body[] = $this->formatText("{$file}:{$range} {$content}");
      }
      $body[] = null;
    }

    $body[] = $this->renderRevisionDetailLink();
    $body[] = null;

    $revision = $this->getRevision();
    $status = $revision->getStatus();

    if ($status == ArcanistDifferentialRevisionStatus::NEEDS_REVISION ||
        $status == ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $diff = $revision->loadActiveDiff();
      if ($diff) {
        $branch = $diff->getBranch();
        if ($branch) {
          $body[] = "BRANCH\n  $branch";
          $body[] = null;
        }
      }
    }

    if ($status == ArcanistDifferentialRevisionStatus::COMMITTED) {
      $phids = $revision->loadCommitPHIDs();
      if ($phids) {
        $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
        if (count($handles) == 1) {
          $body[] = "COMMIT";
        } else {
          // This is unlikely to ever happen since we'll send this mail the
          // first time we discover a commit, but it's not impossible if data
          // was migrated, etc.
          $body[] = "COMMITS";
        }

        foreach ($handles as $handle) {
          $body[] = '  '.PhabricatorEnv::getProductionURI($handle->getURI());
        }
        $body[] = null;
      }
    }

    return implode("\n", $body);
  }
}

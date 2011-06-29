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

class DifferentialCommitMessageData {

  protected $revision;
  protected $fields = array();
  protected $mode;
  protected $comments;

  const MODE_EDIT   = 'edit';
  const MODE_AMEND  = 'amend';

  public function __construct(DifferentialRevision $revision, $mode) {
    $this->revision = $revision;
    $this->mode = $mode;
    $comments = id(new DifferentialComment())->loadAllWhere(
      'revisionID = %d',
      $revision->getID());
    $this->comments = $comments;
  }

  protected function getCommenters() {
    $revision = $this->revision;

    $map = array();
    foreach ($this->comments as $comment) {
      $map[$comment->getAuthorPHID()] = true;
    }

    unset($map[$revision->getAuthorPHID()]);
    if ($this->getReviewer()) {
      unset($map[$this->getReviewer()]);
    }

    return array_keys($map);
  }

  private function getReviewer() {
    $reviewer = null;
    foreach ($this->comments as $comment) {
      if ($comment->getAction() == DifferentialAction::ACTION_ACCEPT) {
        $reviewer = $comment->getAuthorPHID();
      } else if ($comment->getAction() == DifferentialAction::ACTION_REJECT) {
        $reviewer = null;
      }
    }
    return $reviewer;
  }

  public function prepare() {
    $revision = $this->revision;

    $fields = array();
    if ($revision->getSummary()) {
      $fields[] =
        new DifferentialCommitMessageField('Summary', $revision->getSummary());
    }

    $fields[] =
      new DifferentialCommitMessageField('Test Plan', $revision->getTestPlan());

    $reviewer = null;
    $commenters = array();
    $revision->loadRelationships();

    if ($this->mode == self::MODE_AMEND) {
      $reviewer = $this->getReviewer();
      $commenters = $this->getCommenters();
    }

    $reviewers = $revision->getReviewers();
    $ccphids = $revision->getCCPHIDs();

    $phids = array_merge($ccphids, $commenters, $reviewers);
    if ($reviewer) {
      $phids[] = $reviewer;
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    if ($this->mode == self::MODE_AMEND) {
      if ($reviewer) {
        $fields[] =
          new DifferentialCommitMessageField('Reviewed By',
                                             $handles[$reviewer]->getName());
      }
    }

    if ($reviewers) {
      $reviewer_names = array();
      foreach ($reviewers as $uid) {
        $reviewer_names[] = $handles[$uid]->getName();
      }
      $reviewer_names = implode(', ', $reviewer_names);
      $fields[] = new DifferentialCommitMessageField('Reviewers',
                                                     $reviewer_names);
    }

    $user_handles = array_select_keys($handles, $commenters);
    if ($user_handles) {
      $commenters = implode(', ', mpull($user_handles, 'getName'));
      $fields[] = new DifferentialCommitMessageField('Commenters', $commenters);
    }

    $cc_handles = array_select_keys($handles, $ccphids);
    if ($cc_handles) {
      $cc = implode(', ', mpull($cc_handles, 'getName'));
      $fields[] = new DifferentialCommitMessageField('CC', $cc);
    }

    if ($revision->getRevertPlan()) {
      $fields[] =
        new DifferentialCommitMessageField('Revert Plan',
                                           $revision->getRevertPlan());
    }

    if ($revision->getBlameRevision()) {
      $fields[] =
        new DifferentialCommitMessageField('Blame Revision',
                                           $revision->getBlameRevision());
    }

    if ($this->mode == self::MODE_EDIT) {
      // In edit mode, include blank fields.
      $fields += array(
        new DifferentialCommitMessageField('Summary', ''),
        new DifferentialCommitMessageField('Reviewers', ''),
        new DifferentialCommitMessageField('CC', ''),
        new DifferentialCommitMessageField('Revert Plan', ''),
        new DifferentialCommitMessageField('Blame Revision', ''),
      );
    }

    $fields[] =
      new DifferentialCommitMessageField('Title', $revision->getTitle());

    $fields[] = new DifferentialCommitMessageField('Differential Revision',
                                                   $revision->getID());

    $this->fields = $fields;
  }

  public function overwriteFieldValue($name, $value) {
    $field = $this->getField($name);
    if ($field) {
      $field->setValue($value);
    }
    return $this;
  }

  private function getField($name) {
    foreach ($this->fields as $field) {
      if ($field->getName() == $name) {
        return $field;
      }
    }
    return null;
  }

  public function getCommitMessage() {
    $fields = $this->fields;

    $message = array();

    $title = $this->getField('Title');
    $message[] = $title->getValue() . "\n";

    foreach ($fields as $field) {
      if ($field->getName() != 'Title') {
        $message[] = $field->render();
      }
    }

    $message = implode("\n", $message);
    $message = str_replace(
      array("\r\n", "\r"),
      array("\n",   "\n"),
      $message);
    $message = $message."\n";

    return $message;
  }
}

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

    if ($revision->getSummary()) {
      $this->setField('Summary', $revision->getSummary());
    }

    $this->setField('Test Plan', $revision->getTestPlan());

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
        $this->setField('Reviewed By', $handles[$reviewer]->getName());
      }
    }

    if ($reviewers) {
      $reviewer_names = array();
      foreach ($reviewers as $uid) {
        $reviewer_names[] = $handles[$uid]->getName();
      }
      $reviewer_names = implode(', ', $reviewer_names);
      $this->setField('Reviewers', $reviewer_names);
    }

    $user_handles = array_select_keys($handles, $commenters);
    if ($user_handles) {
      $commenters = implode(', ', mpull($user_handles, 'getName'));
      $this->setField('Commenters', $commenters);
    }

    $cc_handles = array_select_keys($handles, $ccphids);
    if ($cc_handles) {
      $cc = implode(', ', mpull($cc_handles, 'getName'));
      $this->setField('CC', $cc);
    }

    if ($revision->getRevertPlan()) {
      $this->setField('Revert Plan', $revision->getRevertPlan());
    }

    if ($revision->getBlameRevision()) {
      $this->setField('Blame Revision', $revision->getBlameRevision());
    }

    if ($this->mode == self::MODE_EDIT) {
      // In edit mode, include blank fields.
      $blank_fields = array('Summary', 'Reviewers', 'CC', 'Revert Plan',
                            'Blame Revision');
      foreach ($blank_fields as $blank_field) {
        if (!$this->getField($blank_field)) {
          $this->setField($blank_field, '');
        }
      }
    }

    $this->setField('Title', $revision->getTitle());
    $this->setField('Differential Revision', $revision->getID());

    // append custom commit message fields
    $modify_class = PhabricatorEnv::getEnvConfig(
      'differential.modify-commit-message-class');

    if ($modify_class) {
      $modifier = newv($modify_class, array($revision));
      $this->fields = $modifier->modifyFields($this->fields);
    }
  }

  public function setField($name, $value) {
    $field = $this->getField($name);
    if ($field) {
      $field->setValue($value);
    } else {
      $this->fields[] = new DifferentialCommitMessageField($name, $value);
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

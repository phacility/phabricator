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

final class DifferentialManiphestTasksFieldSpecification
  extends DifferentialFieldSpecification {

  private $maniphestTasks = array();

  public function shouldAppearOnRevisionView() {
    return PhabricatorEnv::getEnvConfig('maniphest.enabled');
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getManiphestTaskPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'Maniphest Tasks:';
  }

  public function renderValueForRevisionView() {
    $task_phids = $this->getManiphestTaskPHIDs();
    if (!$task_phids) {
      return null;
    }

    $links = array();
    foreach ($task_phids as $task_phid) {
      $links[] = $this->getHandle($task_phid)->renderLink();
    }

    return implode('<br />', $links);
  }

  private function getManiphestTaskPHIDs() {
    $revision = $this->getRevision();
    return $revision->getAttachedPHIDs(
      PhabricatorPHIDConstants::PHID_TYPE_TASK);
  }

  /**
   * Attach the revision to the task(s) and the task(s) to the revision.
   *
   * @return void
   */
  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    $aeditor = new PhabricatorObjectAttachmentEditor(
      PhabricatorPHIDConstants::PHID_TYPE_DREV,
      $editor->getRevision());
    $aeditor->setUser($this->getUser());
    $aeditor->attachObjects(
      PhabricatorPHIDConstants::PHID_TYPE_TASK,
      $this->maniphestTasks,
      $two_way = true);
  }

  protected function didSetRevision() {
    $this->maniphestTasks = $this->getManiphestTaskPHIDs();
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->maniphestTasks;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return PhabricatorEnv::getEnvConfig('maniphest.enabled');
  }

  public function shouldAppearOnCommitMessage() {
    return PhabricatorEnv::getEnvConfig('maniphest.enabled');
  }

  public function getCommitMessageKey() {
    return 'maniphestTaskPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->maniphestTasks = nonempty($value, array());
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Maniphest Tasks';
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Maniphest Task',
      'Maniphest Tasks',
    );
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->maniphestTasks) {
      return null;
    }

    $names = array();
    foreach ($this->maniphestTasks as $phid) {
      $handle = $this->getHandle($phid);
      $names[] = 'T'.$handle->getAlternateID();
    }
    return implode(', ', $names);
  }

  public function parseValueFromCommitMessage($value) {
    $matches = null;
    preg_match_all('/T(\d+)/', $value, $matches);
    if (empty($matches[0])) {
      return array();
    }


    $task_ids = $matches[1];
    $tasks = id(new ManiphestTask())
      ->loadAllWhere('id in (%Ld)', $task_ids);

    $task_phids = array();
    $invalid = array();
    foreach ($task_ids as $task_id) {
      $task = idx($tasks, $task_id);
      if (empty($task)) {
        $invalid[] = 'T'.$task_id;
      } else {
        $task_phids[] = $task->getPHID();
      }
    }

    if ($invalid) {
      if (count($invalid) > 1) {
        $what = 'Maniphest Tasks';
      } else {
        $what = 'Maniphest Task';
      }
      $invalid = implode(', ', $invalid);
      throw new DifferentialFieldParseException(
        "Commit message references nonexistent {$what}: {$invalid}.");
    }

    return $task_phids;
  }

}

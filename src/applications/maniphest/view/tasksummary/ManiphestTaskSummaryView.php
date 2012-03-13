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

/**
 * @group maniphest
 */
final class ManiphestTaskSummaryView extends ManiphestView {

  private $task;
  private $handles;
  private $user;
  private $showBatchControls;

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setShowBatchControls($show_batch_controls) {
    $this->showBatchControls = $show_batch_controls;
    return $this;
  }

  public function render() {

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    $task = $this->task;
    $handles = $this->handles;

    require_celerity_resource('maniphest-task-summary-css');

    $classes = array(
      ManiphestTaskPriority::PRIORITY_UNBREAK_NOW => 'pri-unbreak',
      ManiphestTaskPriority::PRIORITY_TRIAGE => 'pri-triage',
      ManiphestTaskPriority::PRIORITY_HIGH => 'pri-high',
      ManiphestTaskPriority::PRIORITY_NORMAL => 'pri-normal',
      ManiphestTaskPriority::PRIORITY_LOW => 'pri-low',
      ManiphestTaskPriority::PRIORITY_WISH => 'pri-wish',
    );

    $pri_class = idx($classes, $task->getPriority());
    $status_map = ManiphestTaskStatus::getTaskStatusMap();

    $batch = null;
    if ($this->showBatchControls) {
      $batch =
        '<td class="maniphest-task-batch">'.
          javelin_render_tag(
            'input',
            array(
              'type'  => 'checkbox',
              'name'  => 'batch[]',
              'value' => $task->getID(),
              'sigil' => 'maniphest-batch',
            ),
            null).
        '</td>';
    }

    return javelin_render_tag(
      'table',
      array(
        'class' => 'maniphest-task-summary',
        'sigil' => 'maniphest-task',
      ),
      '<tr>'.
        '<td class="maniphest-task-handle '.$pri_class.'">'.
        '</td>'.
        $batch.
        '<td class="maniphest-task-number">'.
          'T'.$task->getID().
        '</td>'.
        '<td class="maniphest-task-status">'.
          idx($status_map, $task->getStatus(), 'Unknown').
        '</td>'.
        '<td class="maniphest-task-owner">'.
          ($task->getOwnerPHID()
            ? $handles[$task->getOwnerPHID()]->renderLink()
            : '<em>None</em>').
        '</td>'.
        '<td class="maniphest-task-name">'.
          phutil_render_tag(
            'a',
            array(
              'href' => '/T'.$task->getID(),
            ),
            phutil_escape_html($task->getTitle())).
        '</td>'.
        '<td class="maniphest-task-priority">'.
          ManiphestTaskPriority::getTaskPriorityName($task->getPriority()).
        '</td>'.
        '<td class="maniphest-task-updated">'.
          phabricator_datetime($task->getDateModified(), $this->user).
        '</td>'.
      '</tr>');
  }

}

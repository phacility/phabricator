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

class ManiphestTaskSummaryView extends AphrontView {

  private $task;
  private $handles;

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function render() {
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

    return
      '<table class="maniphest-task-summary">'.
        '<td class="maniphest-task-number '.$pri_class.'">'.
          'T'.$task->getID().
        '</td>'.
        '<td class="maniphest-task-status">'.
          ($task->getStatus() == ManiphestTaskStatus::STATUS_OPEN
            ? 'Open'
            : 'Closed').
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
          phabricator_format_timestamp($task->getDateModified()).
        '</td>'.
      '</table>';
  }

}

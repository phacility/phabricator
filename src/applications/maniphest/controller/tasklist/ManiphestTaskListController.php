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

class ManiphestTaskListController extends ManiphestController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $views = array(
      'Your Tasks',
      'action'    => 'Action Required',
//      'activity'  => 'Recently Active',
//      'closed'    => 'Recently Closed',
      'created'   => 'Created',
      'triage'    => 'Need Triage',
      '<hr />',
      'All Open Tasks',
      'alltriage'   => 'Need Triage',
      'unassigned'  => 'Unassigned',
      'allopen'     => 'All Open',
    );

    if (empty($views[$this->view])) {
      $this->view = 'action';
    }

    $tasks = $this->loadTasks();

    $nav = new AphrontSideNavView();
    foreach ($views as $view => $name) {
      if (is_integer($view)) {
        $nav->addNavItem(
          phutil_render_tag(
            'span',
            array(),
            $name));
      } else {
        $nav->addNavItem(
          phutil_render_tag(
            'a',
            array(
              'href' => '/maniphest/view/'.$view.'/',
              'class' => ($this->view == $view)
                ? 'aphront-side-nav-selected'
                : null,
            ),
            phutil_escape_html($name)));
      }
    }

    $handle_phids = mpull($tasks, 'getOwnerPHID');
    $handles = id(new PhabricatorObjectHandleData($handle_phids))
      ->loadHandles();

    $task_list = new ManiphestTaskListView();
    $task_list->setTasks($tasks);
    $task_list->setHandles($handles);

    $nav->appendChild(
      '<div style="text-align: right; padding: 1em 1em 0;">'.
        '<a href="/maniphest/task/create/" class="green button">'.
          'Create New Task'.
        '</a>'.
      '</div>');
    $nav->appendChild($task_list);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Task List',
      ));
  }

  private function loadTasks() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = array($user->getPHID());

    switch ($this->view) {
      case 'action':
        return id(new ManiphestTask())->loadAllWhere(
          'ownerPHID in (%Ls) AND status = 0',
          $phids);
      case 'created':
        return id(new ManiphestTask())->loadAllWhere(
          'authorPHID in (%Ls) AND status = 0',
          $phids);
      case 'triage':
        return id(new ManiphestTask())->loadAllWhere(
          'ownerPHID in (%Ls) and status = %d',
          $phids,
          ManiphestTaskPriority::PRIORITY_TRIAGE);
      case 'alltriage':
        return id(new ManiphestTask())->loadAllWhere(
          'status = %d',
          ManiphestTaskPriority::PRIORITY_TRIAGE);
      case 'unassigned':
        return id(new ManiphestTask())->loadAllWhere(
          'ownerPHID IS NULL');
      case 'allopen':
        return id(new ManiphestTask())->loadAllWhere(
          'status = 0');
    }

    return array();
  }


}

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
      'action'    => 'Assigned',
      'created'   => 'Created',
      'triage'    => 'Need Triage',
//      'touched'   => 'Touched',
      '<hr />',
      'All Tasks',
      'alltriage'   => 'Need Triage',
      'unassigned'  => 'Unassigned',
      'all'         => 'All Tasks',
    );

    if (empty($views[$this->view])) {
      $this->view = 'action';
    }

    $request = $this->getRequest();
    $uri = $request->getRequestURI();

    $nav = new AphrontSideNavView();
    foreach ($views as $view => $name) {
      if (is_integer($view)) {
        $nav->addNavItem(
          phutil_render_tag(
            'span',
            array(),
            $name));
      } else {
        $uri->setPath('/maniphest/view/'.$view.'/');
        $nav->addNavItem(
          phutil_render_tag(
            'a',
            array(
              'href' => $uri,
              'class' => ($this->view == $view)
                ? 'aphront-side-nav-selected'
                : null,
            ),
            phutil_escape_html($name)));
      }
    }

    list($status_map, $status_links) = $this->renderStatusLinks();
    list($grouping, $group_links) = $this->renderGroupLinks();
    list($order, $order_links) = $this->renderOrderLinks();

    list($tasks, $handles) = $this->loadTasks(
      array(
        'status'  => $status_map,
        'group'   => $grouping,
        'order'   => $order,
      ));

    require_celerity_resource('maniphest-task-summary-css');

    $nav->appendChild(
      '<div class="maniphest-basic-search-view">'.
        '<div class="maniphest-basic-search-actions">'.
          '<a href="/maniphest/task/create/" class="green button">'.
            'Create New Task'.
          '</a>'.
        '</div>'.
        '<div class="maniphest-basic-search-options">'.
          '<table class="maniphest-basic-search-options-table">'.
            '<tr><th>Status:</th><td>'.$status_links.'</td></tr>'.
            '<tr><th>Group:</th><td>'.$group_links.'</td></tr>'.
            '<tr><th>Order:</th><td>'.$order_links.'</td></tr>'.
          '</table>'.
        '</div>'.
        '<div style="clear: both;"></div>'.
      '</div>');

    $have_tasks = false;
    foreach ($tasks as $group => $list) {
      if (count($list)) {
        $have_tasks = true;
        break;
      }
    }

    if (!$have_tasks) {
      $nav->appendChild(
        '<h1 class="maniphest-task-group-header">'.
          'No matching tasks.'.
        '</h1>');
    } else {
      foreach ($tasks as $group => $list) {
        $task_list = new ManiphestTaskListView();
        $task_list->setTasks($list);
        $task_list->setHandles($handles);

        $nav->appendChild(
          '<h1 class="maniphest-task-group-header">'.
            phutil_escape_html($group).
          '</h1>');
        $nav->appendChild($task_list);
      }
    }

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Task List',
      ));
  }

  private function loadTasks(array $dict) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = array($user->getPHID());

    $task = new ManiphestTask();

    $argv = array();

    $status = $dict['status'];
    if (!empty($status['open']) && !empty($status['closed'])) {
      $status_clause = '1 = 1';
    } else if (!empty($status['open'])) {
      $status_clause = 'status = %d';
      $argv[] = 0;
    } else {
      $status_clause = 'status > %d';
      $argv[] = 0;
    }

    $extra_clause = '1 = 1';
    switch ($this->view) {
      case 'action':
        $extra_clause = 'ownerPHID in (%Ls)';
        $argv[] = $phids;
        break;
      case 'created':
        $extra_clause = 'authorPHID in (%Ls)';
        $argv[] = $phids;
        break;
      case 'triage':
        $extra_clause = 'ownerPHID in (%Ls) AND status = %d';
        $argv[] = $phids;
        $argv[] = ManiphestTaskPriority::PRIORITY_TRIAGE;
        break;
      case 'alltriage':
        $extra_clause = 'status = %d';
        $argv[] = ManiphestTaskPriority::PRIORITY_TRIAGE;
        break;
      case 'unassigned':
        $extra_clause = 'ownerPHID is NULL';
        break;
      case 'all':
        break;
    }

    switch ($dict['order']) {
      case 'priority':
        $order = 'priority DESC, dateModified DESC';
        break;
      case 'created':
        $order = 'id DESC';
        break;
      default:
        $order = 'dateModified DESC';
        break;
    }

    $sql = "({$status_clause}) AND ({$extra_clause}) ORDER BY {$order}";

    array_unshift($argv, $sql);

    $data = call_user_func_array(array($task, 'loadAllWhere'), $argv);

    $handle_phids = mpull($data, 'getOwnerPHID');
    $handles = id(new PhabricatorObjectHandleData($handle_phids))
      ->loadHandles();

    switch ($dict['group']) {
      case 'priority':
        $data = mgroup($data, 'getPriority');
        krsort($data);

        $out = array();
        foreach ($data as $pri => $tasks) {
          $out[ManiphestTaskPriority::getTaskPriorityName($pri)] = $tasks;
        }
        $data = $out;

        break;
      case 'status':
        $data = mgroup($data, 'getStatus');
        ksort($data);

        $out = array();
        foreach ($data as $status => $tasks) {
          $out[ManiphestTaskStatus::getTaskStatusFullName($status)] = $tasks;
        }

        $data = $out;
        break;
      case 'owner':
        $data = mgroup($data, 'getOwnerPHID');

        $out = array();
        foreach ($data as $phid => $tasks) {
          if ($phid) {
            $out[$handles[$phid]->getFullName()] = $tasks;
          } else {
            $out['Unassigned'] = $tasks;
          }
        }
        if (isset($out['Unassigned'])) {
          // If any tasks are unassigned, move them to the front of the list.
          $data = array('Unassigned' => $out['Unassigned']) + $out;
        } else {
          $data = $out;
        }

        ksort($data);
        break;
      default:
        $data = array(
          'Tasks' => $data,
        );
        break;
    }

    return array($data, $handles);
  }

  public function renderStatusLinks() {
    $request = $this->getRequest();

    $sel = array(
      'open'   => false,
      'closed' => false,
    );

    $status = $request->getStr('s');
    if ($status == 'c') {
      $sel['closed'] = true;
    } else if ($status == 'oc') {
      $sel['closed'] = true;
      $sel['open'] = true;
    } else {
      $sel['open'] = true;
    }

    $just_one = (count(array_filter($sel)) == 1);

    $flag_map = array(
      'Open'    => 'open',
      'Closed'  => 'closed',
    );
    $button_names = array(
      'Open'    => 'o',
      'Closed'  => 'c',
    );

    $base_flags = array();
    foreach ($flag_map as $name => $key) {
      $base_flags[$button_names[$name]] = $sel[$key];
    }

    foreach ($button_names as $name => $letter) {
      $flags = $base_flags;
      $flags[$letter] = !$flags[$letter];
      $button_names[$name] = implode('', array_keys(array_filter($flags)));
    }

    $uri = $request->getRequestURI();

    $links = array();
    foreach ($button_names as $name => $value) {
      $selected = $sel[$flag_map[$name]];
      $fixed = ($selected && $just_one);

      $more = null;
      if ($fixed) {
        $href = null;
        $more .= ' toggle-fixed';
      } else {
        $href = $uri->alter('s', $value);
      }

      if ($selected) {
        $more .= ' toggle-selected';
      }

      $links[] = phutil_render_tag(
        'a',
        array(
          'href'  => $href,
          'class' => 'toggle'.$more,
        ),
        $name);
    }
    $status_links = implode("\n", $links);

    return array($sel, $status_links);
  }

  public function renderOrderLinks() {
    $request = $this->getRequest();

    $order = $request->getStr('o');
    $orders = array(
      'u' => 'updated',
      'c' => 'created',
      'p' => 'priority',
    );
    if (empty($orders[$order])) {
      $order = 'p';
    }
    $order_by = $orders[$order];

    $order_names = array(
      'Priority'  => 'p',
      'Updated'   => 'u',
      'Created'   => 'c',
    );

    $uri = $request->getRequestURI();

    $links = array();
    foreach ($order_names as $name => $param_key) {
      if ($param_key == $order) {
        $more = ' toggle-selected toggle-fixed';
        $href = null;
      } else {
        $more = null;
        $href = $uri->alter('o', $param_key);
      }
      $links[] = phutil_render_tag(
        'a',
        array(
          'class' => 'toggle'.$more,
          'href'  => $href,
        ),
        $name);
    }
    $order_links = implode("\n", $links);

    return array($order_by, $order_links);
  }

  public function renderGroupLinks() {
    $request = $this->getRequest();

    $group = $request->getStr('g');
    $groups = array(
      'n' => 'none',
      'p' => 'priority',
      's' => 'status',
      'o' => 'owner',
    );
    if (empty($groups[$group])) {
      $group = 'p';
    }
    $group_by = $groups[$group];

    $group_names = array(
      'Priority'  => 'p',
      'Owner'     => 'o',
      'Status'    => 's',
      'None'      => 'n',
    );

    $uri = $request->getRequestURI();

    $links = array();
    foreach ($group_names as $name => $param_key) {
      if ($param_key == $group) {
        $more = ' toggle-selected toggle-fixed';
        $href = null;
      } else {
        $more = null;
        $href = $uri->alter('g', $param_key);
      }
      $links[] = phutil_render_tag(
        'a',
        array(
          'class' => 'toggle'.$more,
          'href'  => $href,
        ),
        $name);
    }
    $group_links = implode("\n", $links);

    return array($group_by, $group_links);
  }


}

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

    $request = $this->getRequest();
    $user = $request->getUser();
    $uri = $request->getRequestURI();

    if ($request->isFormPost()) {
      $phid_arr = $request->getArr('view_user');
      $view_target = head($phid_arr);
      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI()->alter('phid', $view_target));
    }


    $views = array(
      'User Tasks',
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

    $has_filter = array(
      'action' => true,
      'created' => true,
      'triage' => true,
    );

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

    $view_phid = nonempty($request->getStr('phid'), $user->getPHID());

    list($tasks, $handles) = $this->loadTasks(
      $view_phid,
      array(
        'status'  => $status_map,
        'group'   => $grouping,
        'order'   => $order,
      ));


    $form = id(new AphrontFormView())
      ->setUser($user);

    if (isset($has_filter[$this->view])) {
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLimit(1)
          ->setDatasource('/typeahead/common/users/')
          ->setName('view_user')
          ->setLabel('View User')
          ->setValue(
            array(
              $view_phid => $handles[$view_phid]->getFullName(),
            )));
    }

    $form
      ->appendChild(
        id(new AphrontFormToggleButtonsControl())
          ->setLabel('Status')
          ->setValue($status_links))
      ->appendChild(
        id(new AphrontFormToggleButtonsControl())
          ->setLabel('Group')
          ->setValue($group_links))
      ->appendChild(
        id(new AphrontFormToggleButtonsControl())
          ->setLabel('Order')
          ->setValue($order_links));

    $filter = new AphrontListFilterView();
    $filter->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/maniphest/task/create/',
          'class' => 'green button',
        ),
        'Create New Task'));
    $filter->appendChild($form);

    $nav->appendChild($filter);

    $have_tasks = false;
    foreach ($tasks as $group => $list) {
      if (count($list)) {
        $have_tasks = true;
        break;
      }
    }

    require_celerity_resource('maniphest-task-summary-css');

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

        $count = number_format(count($list));
        $nav->appendChild(
          '<h1 class="maniphest-task-group-header">'.
            phutil_escape_html($group).' ('.$count.')'.
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

  private function loadTasks($view_phid, array $dict) {
    $phids = array($view_phid);

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
        $extra_clause = 'ownerPHID in (%Ls) AND priority = %d';
        $argv[] = $phids;
        $argv[] = ManiphestTaskPriority::PRIORITY_TRIAGE;
        break;
      case 'alltriage':
        $extra_clause = 'priority = %d';
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
    $handle_phids[] = $view_phid;
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

    $statuses = array(
      'o'   => array('open' => true),
      'c'   => array('closed' => true),
      'oc'  => array('open' => true, 'closed' => true),
    );

    $status = $request->getStr('s');
    if (empty($statuses[$status])) {
      $status = 'o';
    }

    $button_names = array(
      'Open'    => 'o',
      'Closed'  => 'c',
      'All'     => 'oc',
    );

    $status_links = $this->renderFilterLinks($button_names, $status, 's');

    return array($statuses[$status], $status_links);
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

    $order_links = $this->renderFilterLinks($order_names, $order, 'o');

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

    $group_links = $this->renderFilterLinks($group_names, $group, 'g');

    return array($group_by, $group_links);
  }

  private function renderFilterLinks($filter_map, $selected, $uri_param) {
    $request = $this->getRequest();
    $uri = $request->getRequestURI();

    $links = array();
    foreach ($filter_map as $name => $value) {
      if ($value == $selected) {
        $more = ' toggle-selected toggle-fixed';
        $href = null;
      } else {
        $more = null;
        $href = $uri->alter($uri_param, $value);
      }
      $links[] = phutil_render_tag(
        'a',
        array(
          'class' => 'toggle'.$more,
          'href'  => $href,
        ),
        $name);
    }
    return implode("\n", $links);
  }

}

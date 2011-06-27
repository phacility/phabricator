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

  const DEFAULT_PAGE_SIZE = 1000;

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

    $page = $request->getInt('page');
    $page_size = self::DEFAULT_PAGE_SIZE;

    list($tasks, $handles, $total_count) = $this->loadTasks(
      $view_phid,
      array(
        'status'  => $status_map,
        'group'   => $grouping,
        'order'   => $order,
        'offset'  => $page,
        'limit'   => $page_size,
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
      $pager = new AphrontPagerView();
      $pager->setURI($request->getRequestURI(), 'page');
      $pager->setPageSize($page_size);
      $pager->setOffset($page);
      $pager->setCount($total_count);

      $cur = ($pager->getOffset() + 1);
      $max = min($pager->getOffset() + $page_size, $total_count);
      $tot = $total_count;

      $cur = number_format($cur);
      $max = number_format($max);
      $tot = number_format($tot);

      $nav->appendChild(
        '<div class="maniphest-total-result-count">'.
          "Displaying tasks {$cur} - {$max} of {$tot}.".
        '</div>');

      foreach ($tasks as $group => $list) {
        $task_list = new ManiphestTaskListView();
        $task_list->setUser($user);
        $task_list->setTasks($list);
        $task_list->setHandles($handles);

        $count = number_format(count($list));
        $nav->appendChild(
          '<h1 class="maniphest-task-group-header">'.
            phutil_escape_html($group).' ('.$count.')'.
          '</h1>');
        $nav->appendChild($task_list);
      }

      $nav->appendChild($pager);
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

    $order = array();
    switch ($dict['group']) {
      case 'priority':
        $order[] = 'priority';
        break;
      case 'owner':
        $order[] = 'ownerOrdering';
        break;
      case 'status':
        $order[] = 'status';
        break;
    }

    switch ($dict['order']) {
      case 'priority':
        $order[] = 'priority';
        $order[] = 'dateModified';
        break;
      case 'created':
        $order[] = 'id';
        break;
      default:
        $order[] = 'dateModified';
        break;
    }

    $order = array_unique($order);

    foreach ($order as $k => $column) {
      switch ($column) {
        case 'ownerOrdering':
          $order[$k] = "{$column} ASC";
          break;
        default:
          $order[$k] = "{$column} DESC";
          break;
      }
    }

    $order = implode(', ', $order);

    $offset = (int)idx($dict, 'offset', 0);
    $limit = (int)idx($dict, 'limit', self::DEFAULT_PAGE_SIZE);

    $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM %T WHERE ".
           "({$status_clause}) AND ({$extra_clause}) ORDER BY {$order} ".
           "LIMIT {$offset}, {$limit}";

    array_unshift($argv, $task->getTableName());

    $conn = $task->establishConnection('r');
    $data = vqueryfx_all($conn, $sql, $argv);

    $total_row_count = queryfx_one($conn, 'SELECT FOUND_ROWS() N');
    $total_row_count = $total_row_count['N'];

    $data = $task->loadAllFromArray($data);

    $handle_phids = mpull($data, 'getOwnerPHID');
    $handle_phids[] = $view_phid;
    $handles = id(new PhabricatorObjectHandleData($handle_phids))
      ->loadHandles();

    switch ($dict['group']) {
      case 'priority':
        $data = mgroup($data, 'getPriority');
        krsort($data);

        // If we have invalid priorities, they'll all map to "???". Merge
        // arrays to prevent them from overwriting each other.

        $out = array();
        foreach ($data as $pri => $tasks) {
          $out[ManiphestTaskPriority::getTaskPriorityName($pri)][] = $tasks;
        }
        foreach ($out as $pri => $tasks) {
          $out[$pri] = array_mergev($tasks);
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

    return array($data, $handles, $total_row_count);
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

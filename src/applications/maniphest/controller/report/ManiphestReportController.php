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
final class ManiphestReportController extends ManiphestController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $uri = $request->getRequestURI();

      $project = head($request->getArr('set_project'));
      $uri = $uri->alter('project', $project);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }


    $base_nav = $this->buildBaseSideNav();
    $base_nav->selectFilter('report', 'report');

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/maniphest/report/'));
    $nav->addLabel('Open Tasks');
    $nav->addFilter('user',           'By User');
    $nav->addFilter('project',        'By Project');
    $nav->addSpacer();
    $nav->addLabel('Burnup');
    $nav->addFilter('burn',           'Burnup Rate');

    $this->view = $nav->selectFilter($this->view, 'user');

    require_celerity_resource('maniphest-report-css');

    switch ($this->view) {
      case 'burn':
        $core = $this->renderBurn();
        break;
      case 'user':
      case 'project':
        $core = $this->renderOpenTasks();
        break;
      default:
        return new Aphront404Response();
    }

    $nav->appendChild($core);
    $base_nav->appendChild($nav);

    return $this->buildStandardPageResponse(
      $base_nav,
      array(
        'title' => 'Maniphest Reports',
      ));
  }

  public function renderBurn() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $handle = null;

    $project_phid = $request->getStr('project');
    if ($project_phid) {
      $phids = array($project_phid);
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
      $handle = $handles[$project_phid];
    }

    $table = new ManiphestTransaction();
    $conn = $table->establishConnection('r');

    $joins = '';
    if ($project_phid) {
      $joins = qsprintf(
        $conn,
        'JOIN %T t ON x.taskID = t.id
          JOIN %T p ON p.taskPHID = t.phid AND p.projectPHID = %s',
        id(new ManiphestTask())->getTableName(),
        id(new ManiphestTaskProject())->getTableName(),
        $project_phid);
    }

    $data = queryfx_all(
      $conn,
      'SELECT x.newValue, x.dateCreated FROM %T x %Q WHERE transactionType = %s
        ORDER BY x.dateCreated ASC',
      $table->getTableName(),
      $joins,
      ManiphestTransactionType::TYPE_STATUS);

    $stats = array();
    $day_buckets = array();

    foreach ($data as $row) {
      $is_close = $row['newValue'];
      $day_bucket = __phabricator_format_local_time(
        $row['dateCreated'],
        $user,
        'z');
      $day_buckets[$day_bucket] = $row['dateCreated'];
      if (empty($stats[$day_bucket])) {
        $stats[$day_bucket] = array(
          'open'  => 0,
          'close' => 0,
        );
      }
      $stats[$day_bucket][$is_close ? 'close' : 'open']++;
    }

    $template = array(
      'open'  => 0,
      'close' => 0,
    );

    $rows = array();
    $rowc = array();
    $last_month = null;
    $last_month_epoch = null;
    $last_week = null;
    $last_week_epoch = null;
    $week = null;
    $month = null;

    $last = key($stats) - 1;
    $period = $template;

    foreach ($stats as $bucket => $info) {
      $epoch = $day_buckets[$bucket];

      $week_bucket = __phabricator_format_local_time(
        $epoch,
        $user,
        'W');
      if ($week_bucket != $last_week) {
        if ($week) {
          $rows[] = $this->formatBurnRow(
            'Week of '.phabricator_date($last_week_epoch, $user),
            $week);
          $rowc[] = 'week';
        }
        $week = $template;
        $last_week = $week_bucket;
        $last_week_epoch = $epoch;
      }

      $month_bucket = __phabricator_format_local_time(
        $epoch,
        $user,
        'm');
      if ($month_bucket != $last_month) {
        if ($month) {
          $rows[] = $this->formatBurnRow(
            __phabricator_format_local_time($last_month_epoch, $user, 'F, Y'),
            $month);
          $rowc[] = 'month';
        }
        $month = $template;
        $last_month = $month_bucket;
        $last_month_epoch = $epoch;
      }

      $rows[] = $this->formatBurnRow(phabricator_date($epoch, $user), $info);
      $rowc[] = null;
      $week['open'] += $info['open'];
      $week['close'] += $info['close'];
      $month['open'] += $info['open'];
      $month['close'] += $info['close'];
      $period['open'] += $info['open'];
      $period['close'] += $info['close'];
    }

    if ($week) {
      $rows[] = $this->formatBurnRow(
        'Week To Date',
        $week);
      $rowc[] = 'week';
    }

    if ($month) {
      $rows[] = $this->formatBurnRow(
        'Month To Date',
        $month);
      $rowc[] = 'month';
    }

    $rows[] = $this->formatBurnRow(
      'All Time',
      $period);
    $rowc[] = 'aggregate';

    $rows = array_reverse($rows);
    $rowc = array_reverse($rowc);

    $table = new AphrontTableView($rows);
    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        'Period',
        'Opened',
        'Closed',
        'Change',
      ));
    $table->setColumnClasses(
      array(
        'right wide',
        'n',
        'n',
        'n',
      ));

    if ($handle) {
      $header = "Task Burn Rate for Project ".$handle->renderLink();
      $caption = "<p>NOTE: This table reflects tasks <em>currently</em> in ".
                 "the project. If a task was opened in the past but added to ".
                 "the project recently, it is counted on the day it was ".
                 "opened, not the day it was categorized. If a task was part ".
                 "of this project in the past but no longer is, it is not ".
                 "counted at all.</p>";
    } else {
      $header = "Task Burn Rate for All Tasks";
      $caption = null;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->setCaption($caption);
    $panel->appendChild($table);

    $tokens = array();
    if ($handle) {
      $tokens = array(
        $handle->getPHID() => $handle->getFullName(),
      );
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setLabel('Project')
          ->setLimit(1)
          ->setName('set_project')
          ->setValue($tokens))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Filter By Project'));

    $filter = new AphrontListFilterView();
    $filter->appendChild($form);

    $id = celerity_generate_unique_node_id();
    $chart = phutil_render_tag(
      'div',
      array(
        'id' => $id,
        'style' => 'border: 1px solid #6f6f6f; '.
                   'margin: 1em 2em; '.
                   'height: 400px; ',
      ),
      '');

    list($open_x, $close_x, $open_y, $close_y) = $this->buildSeries($data);

    require_celerity_resource('raphael-core');
    require_celerity_resource('raphael-g');
    require_celerity_resource('raphael-g-line');

    Javelin::initBehavior('burn-chart', array(
      'hardpoint' => $id,
      'x' => array(
        $open_x,
        $close_x,
      ),
      'y' => array(
        $open_y,
        $close_y,
      ),
    ));

    return array($filter, $chart, $panel);
  }

  private function buildSeries(array $data) {
    $open_count = 0;
    $close_count = 0;

    $open_x = array();
    $open_y = array();
    $close_x = array();
    $close_y = array();

    $start = (int)idx(head($data), 'dateCreated', time());

    $open_x[] = $start;
    $open_y[] = $open_count;
    $close_x[] = $start;
    $close_y[] = $close_count;

    foreach ($data as $row) {
      $t = (int)$row['dateCreated'];
      if ($row['newValue']) {
        ++$close_count;
        $close_x[] = $t;
        $close_y[] = $close_count;
      } else {
        ++$open_count;
        $open_x[] = $t;
        $open_y[] = $open_count;
      }
    }

    $close_x[] = time();
    $close_y[] = $close_count;
    $open_x[] = time();
    $open_y[] = $open_count;

    return array($open_x, $close_x, $open_y, $close_y);
  }

  private function formatBurnRow($label, $info) {
    $delta = $info['open'] - $info['close'];
    $fmt = number_format($delta);
    if ($delta > 0) {
      $fmt = '+'.$fmt;
      $fmt = '<span class="red">'.$fmt.'</span>';
    } else {
      $fmt = '<span class="green">'.$fmt.'</span>';
    }

    return array(
      $label,
      number_format($info['open']),
      number_format($info['close']),
      $fmt);
  }

  public function renderOpenTasks() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new ManiphestTaskQuery())
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN);

    $tasks = $query->execute();

    $date = phabricator_date(time(), $user);

    switch ($this->view) {
      case 'user':
        $result = mgroup($tasks, 'getOwnerPHID');
        $leftover = idx($result, '', array());
        unset($result['']);
        $leftover_name = phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/?users=PHID-!!!!-UP-FOR-GRABS',
          ),
          '(Up For Grabs)');
        $col_header = 'User';
        $header = 'Open Tasks by User and Priority ('.$date.')';
        $link = '/maniphest/?users=';
        break;
      case 'project':
        $result = array();
        foreach ($tasks as $task) {
          $phids = $task->getProjectPHIDs();
          if ($phids) {
            foreach ($phids as $project_phid) {
              $result[$project_phid][] = $task;
            }
          } else {
            $leftover[] = $task;
          }
        }
        $leftover_name = phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/view/all/?projects=PHID-!!!!-NO_PROJECT',
          ),
          '(No Project)');
        $col_header = 'Project';
        $header = 'Open Tasks by Project and Priority ('.$date.')';
        $link = '/maniphest/view/all/?projects=';
        break;
    }

    $phids = array_keys($result);
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $handles = msort($handles, 'getName');

    $order = $request->getStr('order', 'name');

    $rows = array();
    $pri_total = array();
    foreach (array_merge($handles, array(null)) as $handle) {
      if ($handle) {
        $tasks = idx($result, $handle->getPHID(), array());
        $name = phutil_render_tag(
          'a',
          array(
            'href' => $link.$handle->getPHID(),
          ),
          phutil_escape_html($handle->getName()));
      } else {
        $tasks = $leftover;
        $name  = $leftover_name;
      }

      $tasks = mgroup($tasks, 'getPriority');

      $row = array();
      $row[] = $name;
      $total = 0;
      foreach (ManiphestTaskPriority::getTaskPriorityMap() as $pri => $label) {
        $n = count(idx($tasks, $pri, array()));
        if ($n == 0) {
          $row[] = '-';
        } else {
          $row[] = number_format($n);
        }
        $total += $n;
      }
      $row[] = number_format($total);

      switch ($order) {
        case 'total':
          $row['sort'] = $total;
          break;
        case 'name':
        default:
          $row['sort'] = $handle ? $handle->getName() : '~';
          break;
      }

      $rows[] = $row;
    }

    $rows = isort($rows, 'sort');
    foreach ($rows as $k => $row) {
      unset($rows[$k]['sort']);
    }

    $cname = array($col_header);
    $cclass = array('pri right wide');
    foreach (ManiphestTaskPriority::getTaskPriorityMap() as $pri => $label) {
      $cname[] = $label;
      $cclass[] = 'n';
    }
    $cname[] = 'Total';
    $cclass[] = 'n';

    $table = new AphrontTableView($rows);
    $table->setHeaders($cname);
    $table->setColumnClasses($cclass);

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($table);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormToggleButtonsControl())
          ->setLabel('Order')
          ->setValue($order)
          ->setBaseURI($request->getRequestURI(), 'order')
          ->setButtons(
            array(
              'name'  => 'Name',
              'total' => 'Total',
            )));


    $filter = new AphrontListFilterView();
    $filter->appendChild($form);

    return array($filter, $panel);
  }

}

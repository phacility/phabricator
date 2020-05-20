<?php

final class ManiphestReportController extends ManiphestController {

  private $view;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $this->view = $request->getURIData('view');

    if ($request->isFormPost()) {
      $uri = $request->getRequestURI();

      $project = head($request->getArr('set_project'));
      $project = nonempty($project, null);

      if ($project !== null) {
        $uri->replaceQueryParam('project', $project);
      } else {
        $uri->removeQueryParam('project');
      }

      $window = $request->getStr('set_window');
      if ($window !== null) {
        $uri->replaceQueryParam('window', $window);
      } else {
        $uri->removeQueryParam('window');
      }

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/maniphest/report/'));
    $nav->addLabel(pht('Open Tasks'));
    $nav->addFilter('user', pht('By User'));
    $nav->addFilter('project', pht('By Project'));

    $class = 'PhabricatorFactApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $nav->addLabel(pht('Burnup'));
      $nav->addFilter('burn', pht('Burnup Rate'));
    }

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

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Reports'));

    $nav->appendChild($core);
    $title = pht('Maniphest Reports');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav);

  }

  public function renderBurn() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $handle = null;

    $project_phid = $request->getStr('project');
    if ($project_phid) {
      $phids = array($project_phid);
      $handles = $this->loadViewerHandles($phids);
      $handle = $handles[$project_phid];
    }

    $table = new ManiphestTransaction();
    $conn = $table->establishConnection('r');

    if ($project_phid) {
      $joins = qsprintf(
        $conn,
        'JOIN %T t ON x.objectPHID = t.phid
          JOIN %T p ON p.src = t.phid AND p.type = %d AND p.dst = %s',
        id(new ManiphestTask())->getTableName(),
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        $project_phid);
      $create_joins = qsprintf(
        $conn,
        'JOIN %T p ON p.src = t.phid AND p.type = %d AND p.dst = %s',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        $project_phid);
    } else {
      $joins = qsprintf($conn, '');
      $create_joins = qsprintf($conn, '');
    }

    $data = queryfx_all(
      $conn,
      'SELECT x.transactionType, x.oldValue, x.newValue, x.dateCreated
        FROM %T x %Q
        WHERE transactionType IN (%Ls)
        ORDER BY x.dateCreated ASC',
      $table->getTableName(),
      $joins,
      array(
        ManiphestTaskStatusTransaction::TRANSACTIONTYPE,
        ManiphestTaskMergedIntoTransaction::TRANSACTIONTYPE,
      ));

    // See PHI273. After the move to EditEngine, we no longer create a
    // "status" transaction if a task is created directly into the default
    // status. This likely impacted API/email tasks after 2016 and all other
    // tasks after late 2017. Until Facts can fix this properly, use the
    // task creation dates to generate synthetic transactions which look like
    // the older transactions that this page expects.

    $default_status = ManiphestTaskStatus::getDefaultStatus();
    $duplicate_status = ManiphestTaskStatus::getDuplicateStatus();

    // Build synthetic transactions which take status from `null` to the
    // default value.
    $create_rows = queryfx_all(
      $conn,
      'SELECT t.dateCreated FROM %T t %Q',
      id(new ManiphestTask())->getTableName(),
      $create_joins);
    foreach ($create_rows as $key => $create_row) {
      $create_rows[$key] = array(
        'transactionType' => 'status',
        'oldValue' => null,
        'newValue' => $default_status,
        'dateCreated' => $create_row['dateCreated'],
      );
    }

    // Remove any actual legacy status transactions which take status from
    // `null` to any open status.
    foreach ($data as $key => $row) {
      if ($row['transactionType'] != 'status') {
        continue;
      }

      $oldv = trim($row['oldValue'], '"');
      $newv = trim($row['newValue'], '"');

      // If this is a status change, preserve it.
      if ($oldv != 'null') {
        continue;
      }

      // If this task was created directly into a closed status, preserve
      // the transaction.
      if (!ManiphestTaskStatus::isOpenStatus($newv)) {
        continue;
      }

      // If this is a legacy "create" transaction, discard it in favor of the
      // synthetic one.
      unset($data[$key]);
    }

    // Merge the synthetic rows into the real transactions.
    $data = array_merge($create_rows, $data);
    $data = array_values($data);
    $data = isort($data, 'dateCreated');

    $stats = array();
    $day_buckets = array();

    $open_tasks = array();

    foreach ($data as $key => $row) {
      switch ($row['transactionType']) {
        case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
          // NOTE: Hack to avoid json_decode().
          $oldv = trim($row['oldValue'], '"');
          $newv = trim($row['newValue'], '"');
          break;
        case ManiphestTaskMergedIntoTransaction::TRANSACTIONTYPE:
          // NOTE: Merging a task does not generate a "status" transaction.
          // We pretend it did. Note that this is not always accurate: it is
          // possible to merge a task which was previously closed, but this
          // fake transaction always counts a merge as a closure.
          $oldv = $default_status;
          $newv = $duplicate_status;
          break;
      }

      if ($oldv == 'null') {
        $old_is_open = false;
      } else {
        $old_is_open = ManiphestTaskStatus::isOpenStatus($oldv);
      }

      $new_is_open = ManiphestTaskStatus::isOpenStatus($newv);

      $is_open  = ($new_is_open && !$old_is_open);
      $is_close = ($old_is_open && !$new_is_open);

      $data[$key]['_is_open'] = $is_open;
      $data[$key]['_is_close'] = $is_close;

      if (!$is_open && !$is_close) {
        // This is either some kind of bogus event, or a resolution change
        // (e.g., resolved -> invalid). Just skip it.
        continue;
      }

      $day_bucket = phabricator_format_local_time(
        $row['dateCreated'],
        $viewer,
        'Yz');
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

    $last = last_key($stats) - 1;
    $period = $template;

    foreach ($stats as $bucket => $info) {
      $epoch = $day_buckets[$bucket];

      $week_bucket = phabricator_format_local_time(
        $epoch,
        $viewer,
        'YW');
      if ($week_bucket != $last_week) {
        if ($week) {
          $rows[] = $this->formatBurnRow(
            pht('Week of %s', phabricator_date($last_week_epoch, $viewer)),
            $week);
          $rowc[] = 'week';
        }
        $week = $template;
        $last_week = $week_bucket;
        $last_week_epoch = $epoch;
      }

      $month_bucket = phabricator_format_local_time(
        $epoch,
        $viewer,
        'Ym');
      if ($month_bucket != $last_month) {
        if ($month) {
          $rows[] = $this->formatBurnRow(
            phabricator_format_local_time($last_month_epoch, $viewer, 'F, Y'),
            $month);
          $rowc[] = 'month';
        }
        $month = $template;
        $last_month = $month_bucket;
        $last_month_epoch = $epoch;
      }

      $rows[] = $this->formatBurnRow(phabricator_date($epoch, $viewer), $info);
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
        pht('Week To Date'),
        $week);
      $rowc[] = 'week';
    }

    if ($month) {
      $rows[] = $this->formatBurnRow(
        pht('Month To Date'),
        $month);
      $rowc[] = 'month';
    }

    $rows[] = $this->formatBurnRow(
      pht('All Time'),
      $period);
    $rowc[] = 'aggregate';

    $rows = array_reverse($rows);
    $rowc = array_reverse($rowc);

    $table = new AphrontTableView($rows);
    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        pht('Period'),
        pht('Opened'),
        pht('Closed'),
        pht('Change'),
      ));
    $table->setColumnClasses(
      array(
        'right wide',
        'n',
        'n',
        'n',
      ));

    if ($handle) {
      $inst = pht(
        'NOTE: This table reflects tasks currently in '.
        'the project. If a task was opened in the past but added to '.
        'the project recently, it is counted on the day it was '.
        'opened, not the day it was categorized. If a task was part '.
        'of this project in the past but no longer is, it is not '.
        'counted at all. This table may not agree exactly with the chart '.
        'above.');
      $header = pht('Task Burn Rate for Project %s', $handle->renderLink());
      $caption = phutil_tag('p', array(), $inst);
    } else {
      $header = pht('Task Burn Rate for All Tasks');
      $caption = null;
    }

    if ($caption) {
      $caption = id(new PHUIInfoView())
        ->appendChild($caption)
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
    }

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText($header);
    if ($caption) {
      $panel->setInfoView($caption);
    }
    $panel->setTable($table);

    $tokens = array();
    if ($handle) {
      $tokens = array($handle);
    }

    $filter = $this->renderReportFilters($tokens, $has_window = false);

    $id = celerity_generate_unique_node_id();
    $chart = phutil_tag(
      'div',
      array(
        'id' => $id,
        'style' => 'border: 1px solid #BFCFDA; '.
                   'background-color: #fff; '.
                   'margin: 8px 16px; '.
                   'height: 400px; ',
      ),
      '');

    list($burn_x, $burn_y) = $this->buildSeries($data);

    if ($project_phid) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($project_phid))
        ->execute();
    } else {
      $projects = array();
    }

    $panel = id(new PhabricatorProjectBurndownChartEngine())
      ->setViewer($viewer)
      ->setProjects($projects)
      ->buildChartPanel();

    $panel->setName(pht('Burnup Rate'));

    $chart_view = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->setParentPanelPHIDs(array())
      ->renderPanel();

    return array($filter, $chart_view);
  }

  private function renderReportFilters(array $tokens, $has_window) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectDatasource())
          ->setLabel(pht('Project'))
          ->setLimit(1)
          ->setName('set_project')
          // TODO: This is silly, but this is Maniphest reports.
          ->setValue(mpull($tokens, 'getPHID')));

    if ($has_window) {
      list($window_str, $ignored, $window_error) = $this->getWindow();
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('Recently Means'))
            ->setName('set_window')
            ->setCaption(
              pht('Configure the cutoff for the "Recently Closed" column.'))
            ->setValue($window_str)
            ->setError($window_error));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Filter By Project')));

    $filter = new AphrontListFilterView();
    $filter->appendChild($form);

    return $filter;
  }

  private function buildSeries(array $data) {
    $out = array();

    $counter = 0;
    foreach ($data as $row) {
      $t = (int)$row['dateCreated'];
      if ($row['_is_close']) {
        --$counter;
        $out[$t] = $counter;
      } else if ($row['_is_open']) {
        ++$counter;
        $out[$t] = $counter;
      }
    }

    return array(array_keys($out), array_values($out));
  }

  private function formatBurnRow($label, $info) {
    $delta = $info['open'] - $info['close'];
    $fmt = number_format($delta);
    if ($delta > 0) {
      $fmt = '+'.$fmt;
      $fmt = phutil_tag('span', array('class' => 'red'), $fmt);
    } else {
      $fmt = phutil_tag('span', array('class' => 'green'), $fmt);
    }

    return array(
      $label,
      number_format($info['open']),
      number_format($info['close']),
      $fmt,
    );
  }

  public function renderOpenTasks() {
    $request = $this->getRequest();
    $viewer = $request->getUser();


    $query = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants());

    switch ($this->view) {
      case 'project':
        $query->needProjectPHIDs(true);
        break;
    }

    $project_phid = $request->getStr('project');
    $project_handle = null;
    if ($project_phid) {
      $phids = array($project_phid);
      $handles = $this->loadViewerHandles($phids);
      $project_handle = $handles[$project_phid];

      $query->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_OR,
        $phids);
    }

    $tasks = $query->execute();

    $recently_closed = $this->loadRecentlyClosedTasks();

    $date = phabricator_date(time(), $viewer);

    switch ($this->view) {
      case 'user':
        $result = mgroup($tasks, 'getOwnerPHID');
        $leftover = idx($result, '', array());
        unset($result['']);

        $result_closed = mgroup($recently_closed, 'getOwnerPHID');
        $leftover_closed = idx($result_closed, '', array());
        unset($result_closed['']);

        $base_link = '/maniphest/?assigned=';
        $leftover_name = phutil_tag('em', array(), pht('(Up For Grabs)'));
        $col_header = pht('User');
        $header = pht('Open Tasks by User and Priority (%s)', $date);
        break;
      case 'project':
        $result = array();
        $leftover = array();
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

        $result_closed = array();
        $leftover_closed = array();
        foreach ($recently_closed as $task) {
          $phids = $task->getProjectPHIDs();
          if ($phids) {
            foreach ($phids as $project_phid) {
              $result_closed[$project_phid][] = $task;
            }
          } else {
            $leftover_closed[] = $task;
          }
        }

        $base_link = '/maniphest/?projects=';
        $leftover_name = phutil_tag('em', array(), pht('(No Project)'));
        $col_header = pht('Project');
        $header = pht('Open Tasks by Project and Priority (%s)', $date);
        break;
    }

    $phids = array_keys($result);
    $handles = $this->loadViewerHandles($phids);
    $handles = msort($handles, 'getName');

    $order = $request->getStr('order', 'name');
    list($order, $reverse) = AphrontTableView::parseSort($order);

    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-tooltips', array());

    $rows = array();
    $pri_total = array();
    foreach (array_merge($handles, array(null)) as $handle) {
      if ($handle) {
        if (($project_handle) &&
            ($project_handle->getPHID() == $handle->getPHID())) {
          // If filtering by, e.g., "bugs", don't show a "bugs" group.
          continue;
        }

        $tasks = idx($result, $handle->getPHID(), array());
        $name = phutil_tag(
          'a',
          array(
            'href' => $base_link.$handle->getPHID(),
          ),
          $handle->getName());
        $closed = idx($result_closed, $handle->getPHID(), array());
      } else {
        $tasks = $leftover;
        $name  = $leftover_name;
        $closed = $leftover_closed;
      }

      $taskv = $tasks;
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

      list($link, $oldest_all) = $this->renderOldest($taskv);
      $row[] = $link;

      $normal_or_better = array();
      foreach ($taskv as $id => $task) {
        // TODO: This is sort of a hard-code for the default "normal" status.
        // When reports are more powerful, this should be made more general.
        if ($task->getPriority() < 50) {
          continue;
        }
        $normal_or_better[$id] = $task;
      }

      list($link, $oldest_pri) = $this->renderOldest($normal_or_better);
      $row[] = $link;

      if ($closed) {
        $task_ids = implode(',', mpull($closed, 'getID'));
        $row[] = phutil_tag(
          'a',
          array(
            'href' => '/maniphest/?ids='.$task_ids,
            'target' => '_blank',
          ),
          number_format(count($closed)));
      } else {
        $row[] = '-';
      }

      switch ($order) {
        case 'total':
          $row['sort'] = $total;
          break;
        case 'oldest-all':
          $row['sort'] = $oldest_all;
          break;
        case 'oldest-pri':
          $row['sort'] = $oldest_pri;
          break;
        case 'closed':
          $row['sort'] = count($closed);
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
    if ($reverse) {
      $rows = array_reverse($rows);
    }

    $cname = array($col_header);
    $cclass = array('pri right wide');
    $pri_map = ManiphestTaskPriority::getShortNameMap();
    foreach ($pri_map as $pri => $label) {
      $cname[] = $label;
      $cclass[] = 'n';
    }
    $cname[] = pht('Total');
    $cclass[] = 'n';
    $cname[] = javelin_tag(
      'span',
      array(
        'sigil' => 'has-tooltip',
        'meta'  => array(
          'tip' => pht('Oldest open task.'),
          'size' => 200,
        ),
      ),
      pht('Oldest (All)'));
    $cclass[] = 'n';
    $cname[] = javelin_tag(
      'span',
      array(
        'sigil' => 'has-tooltip',
        'meta'  => array(
          'tip' => pht(
            'Oldest open task, excluding those with Low or Wishlist priority.'),
          'size' => 200,
        ),
      ),
      pht('Oldest (Pri)'));
    $cclass[] = 'n';

    list($ignored, $window_epoch) = $this->getWindow();
    $edate = phabricator_datetime($window_epoch, $viewer);
    $cname[] = javelin_tag(
      'span',
      array(
        'sigil' => 'has-tooltip',
        'meta'  => array(
          'tip'  => pht('Closed after %s', $edate),
          'size' => 260,
        ),
      ),
      pht('Recently Closed'));
    $cclass[] = 'n';

    $table = new AphrontTableView($rows);
    $table->setHeaders($cname);
    $table->setColumnClasses($cclass);
    $table->makeSortable(
      $request->getRequestURI(),
      'order',
      $order,
      $reverse,
      array(
        'name',
        null,
        null,
        null,
        null,
        null,
        null,
        'total',
        'oldest-all',
        'oldest-pri',
        'closed',
      ));

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText($header);
    $panel->setTable($table);

    $tokens = array();
    if ($project_handle) {
      $tokens = array($project_handle);
    }
    $filter = $this->renderReportFilters($tokens, $has_window = true);

    return array($filter, $panel);
  }


  /**
   * Load all the tasks that have been recently closed.
   */
  private function loadRecentlyClosedTasks() {
    list($ignored, $window_epoch) = $this->getWindow();

    $table = new ManiphestTask();
    $xtable = new ManiphestTransaction();
    $conn_r = $table->establishConnection('r');

    // TODO: Gross. This table is not meant to be queried like this. Build
    // real stats tables.

    $open_status_list = array();
    foreach (ManiphestTaskStatus::getOpenStatusConstants() as $constant) {
      $open_status_list[] = json_encode((string)$constant);
    }

    $rows = queryfx_all(
      $conn_r,
      'SELECT t.id FROM %T t JOIN %T x ON x.objectPHID = t.phid
        WHERE t.status NOT IN (%Ls)
        AND x.oldValue IN (null, %Ls)
        AND x.newValue NOT IN (%Ls)
        AND t.dateModified >= %d
        AND x.dateCreated >= %d',
      $table->getTableName(),
      $xtable->getTableName(),
      ManiphestTaskStatus::getOpenStatusConstants(),
      $open_status_list,
      $open_status_list,
      $window_epoch,
      $window_epoch);

    if (!$rows) {
      return array();
    }

    $ids = ipull($rows, 'id');

    $query = id(new ManiphestTaskQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withIDs($ids);

    switch ($this->view) {
      case 'project':
        $query->needProjectPHIDs(true);
        break;
    }

    return $query->execute();
  }

  /**
   * Parse the "Recently Means" filter into:
   *
   *    - A string representation, like "12 AM 7 days ago" (default);
   *    - a locale-aware epoch representation; and
   *    - a possible error.
   */
  private function getWindow() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $window_str = $this->getRequest()->getStr('window', '12 AM 7 days ago');

    $error = null;
    $window_epoch = null;

    // Do locale-aware parsing so that the user's timezone is assumed for
    // time windows like "3 PM", rather than assuming the server timezone.

    $window_epoch = PhabricatorTime::parseLocalTime($window_str, $viewer);
    if (!$window_epoch) {
      $error = 'Invalid';
      $window_epoch = time() - (60 * 60 * 24 * 7);
    }

    // If the time ends up in the future, convert it to the corresponding time
    // and equal distance in the past. This is so users can type "6 days" (which
    // means "6 days from now") and get the behavior of "6 days ago", rather
    // than no results (because the window epoch is in the future). This might
    // be a little confusing because it causes "tomorrow" to mean "yesterday"
    // and "2022" (or whatever) to mean "ten years ago", but these inputs are
    // nonsense anyway.

    if ($window_epoch > time()) {
      $window_epoch = time() - ($window_epoch - time());
    }

    return array($window_str, $window_epoch, $error);
  }

  private function renderOldest(array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');
    $oldest = null;
    foreach ($tasks as $id => $task) {
      if (($oldest === null) ||
          ($task->getDateCreated() < $tasks[$oldest]->getDateCreated())) {
        $oldest = $id;
      }
    }

    if ($oldest === null) {
      return array('-', 0);
    }

    $oldest = $tasks[$oldest];

    $raw_age = (time() - $oldest->getDateCreated());
    $age = number_format($raw_age / (24 * 60 * 60)).' d';

    $link = javelin_tag(
      'a',
      array(
        'href'  => '/T'.$oldest->getID(),
        'sigil' => 'has-tooltip',
        'meta'  => array(
          'tip' => 'T'.$oldest->getID().': '.$oldest->getTitle(),
        ),
        'target' => '_blank',
      ),
      $age);

    return array($link, $raw_age);
  }

}

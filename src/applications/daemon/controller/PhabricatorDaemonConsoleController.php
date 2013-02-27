<?php

final class PhabricatorDaemonConsoleController
  extends PhabricatorDaemonController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $completed = id(new PhabricatorWorkerArchiveTask())->loadAllWhere(
      'dateModified > %d',
      time() - (60 * 15));

    $completed_info = array();
    foreach ($completed as $completed_task) {
      $class = $completed_task->getTaskClass();
      if (empty($completed_info[$class])) {
        $completed_info[$class] = array(
          'n' => 0,
          'duration' => 0,
        );
      }
      $completed_info[$class]['n']++;
      $duration = $completed_task->getDuration();
      $completed_info[$class]['duration'] += $duration;
    }

    $completed_info = isort($completed_info, 'n');

    $rows = array();
    foreach ($completed_info as $class => $info) {
      $rows[] = array(
        $class,
        number_format($info['n']),
        number_format((int)($info['duration'] / $info['n'])).' us',
      );
    }

    $completed_table = new AphrontTableView($rows);
    $completed_table->setNoDataString(
      pht('No tasks have completed in the last 15 minutes.'));
    $completed_table->setHeaders(
      array(
        pht('Class'),
        pht('Count'),
        pht('Avg'),
      ));
    $completed_table->setColumnClasses(
      array(
        'wide',
        'n',
        'n',
      ));

    $completed_panel = new AphrontPanelView();
    $completed_panel->setHeader(pht('Recently Completed Tasks (15m)'));
    $completed_panel->appendChild($completed_table);
    $completed_panel->setNoBackground();

    $logs = id(new PhabricatorDaemonLog())->loadAllWhere(
      '`status` = %s ORDER BY id DESC',
      'run');

    $daemon_table = new PhabricatorDaemonLogListView();
    $daemon_table->setUser($user);
    $daemon_table->setDaemonLogs($logs);

    $daemon_panel = new AphrontPanelView();
    $daemon_panel->setHeader(pht('Active Daemons'));
    $daemon_panel->appendChild($daemon_table);
    $daemon_panel->setNoBackground();

    $tasks = id(new PhabricatorWorkerActiveTask())->loadAllWhere(
      'leaseOwner IS NOT NULL');

    $rows = array();
    foreach ($tasks as $task) {
      $rows[] = array(
        $task->getID(),
        $task->getTaskClass(),
        $task->getLeaseOwner(),
        $task->getLeaseExpires() - time(),
        $task->getFailureCount(),
        phutil_tag(
          'a',
          array(
            'href' => '/daemon/task/'.$task->getID().'/',
            'class' => 'button small grey',
          ),
          pht('View Task')),
      );
    }

    $leased_table = new AphrontTableView($rows);
    $leased_table->setHeaders(
      array(
        pht('ID'),
        pht('Class'),
        pht('Owner'),
        pht('Expires'),
        pht('Failures'),
        '',
      ));
    $leased_table->setColumnClasses(
      array(
        'n',
        'wide',
        '',
        '',
        'n',
        'action',
      ));
    $leased_table->setNoDataString(pht('No tasks are leased by workers.'));

    $leased_panel = new AphrontPanelView();
    $leased_panel->setHeader('Leased Tasks');
    $leased_panel->appendChild($leased_table);
    $leased_panel->setNoBackground();

    $task_table = new PhabricatorWorkerActiveTask();
    $queued = queryfx_all(
      $task_table->establishConnection('r'),
      'SELECT taskClass, count(*) N FROM %T GROUP BY taskClass
        ORDER BY N DESC',
      $task_table->getTableName());

    $rows = array();
    foreach ($queued as $row) {
      $rows[] = array(
        $row['taskClass'],
        number_format($row['N']),
      );
    }

    $queued_table = new AphrontTableView($rows);
    $queued_table->setHeaders(
      array(
        pht('Class'),
        pht('Count'),
      ));
    $queued_table->setColumnClasses(
      array(
        'wide',
        'n',
      ));
    $queued_table->setNoDataString(pht('Task queue is empty.'));

    $queued_panel = new AphrontPanelView();
    $queued_panel->setHeader(pht('Queued Tasks'));
    $queued_panel->appendChild($queued_table);
    $queued_panel->setNoBackground();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');
    $nav->appendChild(
      array(
        $completed_panel,
        $daemon_panel,
        $queued_panel,
        $leased_panel,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Console'),
      ));
  }

}

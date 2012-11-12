<?php

final class PhabricatorDaemonConsoleController
  extends PhabricatorDaemonController {

  public function processRequest() {
    $logs = id(new PhabricatorDaemonLog())->loadAllWhere(
      '`status` != %s ORDER BY id DESC LIMIT 15', 'exit');

    $request = $this->getRequest();
    $user = $request->getUser();

    $daemon_table = new PhabricatorDaemonLogListView();
    $daemon_table->setUser($user);
    $daemon_table->setDaemonLogs($logs);

    $daemon_panel = new AphrontPanelView();
    $daemon_panel->setHeader('Recently Launched Daemons');
    $daemon_panel->appendChild($daemon_table);

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
        phutil_render_tag(
          'a',
          array(
            'href' => '/daemon/task/'.$task->getID().'/',
            'class' => 'button small grey',
          ),
          'View Task'),
      );
    }

    $leased_table = new AphrontTableView($rows);
    $leased_table->setHeaders(
      array(
        'ID',
        'Class',
        'Owner',
        'Expires',
        'Failures',
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
    $leased_table->setNoDataString('No tasks are leased by workers.');

    $leased_panel = new AphrontPanelView();
    $leased_panel->setHeader('Leased Tasks');
    $leased_panel->appendChild($leased_table);

    $task_table = new PhabricatorWorkerActiveTask();
    $queued = queryfx_all(
      $task_table->establishConnection('r'),
      'SELECT taskClass, count(*) N FROM %T GROUP BY taskClass
        ORDER BY N DESC',
      $task_table->getTableName());

    $rows = array();
    foreach ($queued as $row) {
      $rows[] = array(
        phutil_escape_html($row['taskClass']),
        number_format($row['N']),
      );
    }

    $queued_table = new AphrontTableView($rows);
    $queued_table->setHeaders(
      array(
        'Class',
        'Count',
      ));
    $queued_table->setColumnClasses(
      array(
        'wide',
        'n',
      ));
    $queued_table->setNoDataString('Task queue is empty.');

    $queued_panel = new AphrontPanelView();
    $queued_panel->setHeader('Queued Tasks');
    $queued_panel->appendChild($queued_table);

    $cursors = id(new PhabricatorTimelineCursor())
      ->loadAll();

    $rows = array();
    foreach ($cursors as $cursor) {
      $rows[] = array(
        phutil_escape_html($cursor->getName()),
        number_format($cursor->getPosition()),
      );
    }

    $cursor_table = new AphrontTableView($rows);
    $cursor_table->setHeaders(
      array(
        'Name',
        'Position',
      ));
    $cursor_table->setColumnClasses(
      array(
        'wide',
        'n',
      ));
    $cursor_table->setNoDataString('No timeline cursors exist.');

    $cursor_panel = new AphrontPanelView();
    $cursor_panel->setHeader('Timeline Cursors');
    $cursor_panel->appendChild($cursor_table);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('');
    $nav->appendChild(
      array(
        $daemon_panel,
        $cursor_panel,
        $queued_panel,
        $leased_panel,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Console',
      ));
  }

}

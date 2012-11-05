<?php

final class PhabricatorDaemonLogListController
  extends PhabricatorDaemonController {

  private $running;

  public function willProcessRequest(array $data) {
    $this->running = !empty($data['running']);
  }

  public function processRequest() {
    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    $clause = '1 = 1';
    if ($this->running) {
      $clause = "`status` != 'exit'";
    }

    $logs = id(new PhabricatorDaemonLog())->loadAllWhere(
      '%Q ORDER BY id DESC LIMIT %d, %d',
      $clause,
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $logs = $pager->sliceResults($logs);
    $pager->setURI($request->getRequestURI(), 'page');

    $daemon_table = new PhabricatorDaemonLogListView();
    $daemon_table->setUser($request->getUser());
    $daemon_table->setDaemonLogs($logs);

    $daemon_panel = new AphrontPanelView();
    $daemon_panel->setHeader('Launched Daemons');
    $daemon_panel->appendChild($daemon_table);
    $daemon_panel->appendChild($pager);

    $nav = $this->buildSideNavView();
    $nav->selectFilter($this->running ? 'log/running' : 'log');
    $nav->appendChild($daemon_panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $this->running ? 'Running Daemons' : 'All Daemons',
      ));
  }

}

<?php

final class PhabricatorDaemonLogListController
  extends PhabricatorDaemonController {

  public function processRequest() {
    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    $clause = '1 = 1';

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
    $daemon_panel->setHeader(pht('All Daemons'));
    $daemon_panel->appendChild($daemon_table);
    $daemon_panel->appendChild($pager);
    $daemon_panel->setNoBackground();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('log');
    $nav->appendChild($daemon_panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('All Daemons'),
      ));
  }

}

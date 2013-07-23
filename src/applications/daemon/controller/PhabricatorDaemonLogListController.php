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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('All Daemons')));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('log');
    $nav->setCrumbs($crumbs);
    $nav->appendChild($daemon_table);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('All Daemons'),
        'device' => true,
        'dust' => true,
      ));
  }

}

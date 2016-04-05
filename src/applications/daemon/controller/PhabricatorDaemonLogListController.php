<?php

final class PhabricatorDaemonLogListController
  extends PhabricatorDaemonController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $logs = id(new PhabricatorDaemonLogQuery())
      ->setViewer($viewer)
      ->setAllowStatusWrites(true)
      ->executeWithCursorPager($pager);

    $daemon_table = new PhabricatorDaemonLogListView();
    $daemon_table->setUser($request->getUser());
    $daemon_table->setDaemonLogs($logs);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('All Daemons'))
      ->appendChild($daemon_table);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('All Daemons'));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('log');
    $nav->setCrumbs($crumbs);
    $nav->appendChild($box);
    $nav->appendChild($pager);

    return $this->newPage()
      ->setTitle(pht('All Daemons'))
      ->appendChild($nav);

  }

}

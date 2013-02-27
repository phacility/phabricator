<?php

final class PhabricatorDaemonCombinedLogController
  extends PhabricatorDaemonController {


  public function processRequest() {
    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setPageSize(1000);

    $events = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $events = $pager->sliceResults($events);
    $pager->setURI($request->getRequestURI(), 'page');

    $event_view = new PhabricatorDaemonLogEventsView();
    $event_view->setEvents($events);
    $event_view->setUser($request->getUser());
    $event_view->setCombinedLog(true);

    $log_panel = new AphrontPanelView();
    $log_panel->setHeader('Combined Daemon Logs');
    $log_panel->appendChild($event_view);
    $log_panel->appendChild($pager);
    $log_panel->setNoBackground();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('log/combined');
    $nav->appendChild($log_panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Combined Daemon Log'),
      ));
  }

}

<?php

final class DrydockLogController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNav('log');

    $query = new DrydockLogQuery();

    $resource_ids = $request->getStrList('resource');
    if ($resource_ids) {
      $query->withResourceIDs($resource_ids);
    }

    $lease_ids = $request->getStrList('lease');
    if ($lease_ids) {
      $query->withLeaseIDs($lease_ids);
    }

    $pager = new AphrontPagerView();
    $pager->setPageSize(500);
    $pager->setOffset($request->getInt('offset'));
    $pager->setURI($request->getRequestURI(), 'offset');

    $logs = $query->executeWithOffsetPager($pager);

    $rows = array();
    foreach ($logs as $log) {
      $rows[] = array(
        $log->getResourceID(),
        $log->getLeaseID(),
        phutil_escape_html($log->getMessage()),
        phabricator_datetime($log->getEpoch(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Resource',
        'Lease',
        'Message',
        'Date',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Drydock Logs');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Logs',
      ));

  }

}

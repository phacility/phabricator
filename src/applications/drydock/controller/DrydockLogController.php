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

    $table = $this->buildLogTableView($logs);
    $table->appendChild($pager);

    $nav->appendChild($table);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Logs',
      ));

  }

}

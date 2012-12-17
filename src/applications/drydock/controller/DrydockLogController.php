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

    $title = pht('Logs');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $table = $this->buildLogTableView($logs);
    $table->appendChild($pager);

    $nav->appendChild(
      array(
        $header,
        $table,
        $pager,
      ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($this->getApplicationURI('/logs/')));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));

  }

}

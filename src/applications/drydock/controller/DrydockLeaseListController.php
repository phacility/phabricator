<?php

final class DrydockLeaseListController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNav('lease');

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI('/drydock/lease/'), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $leases = id(new DrydockLeaseQuery())
      ->needResources(true)
      ->executeWithOffsetPager($pager);

    $title = pht('Leases');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $lease_list = $this->buildLeaseListView($leases);

    $nav->appendChild(
      array(
        $header,
        $lease_list,
        $pager,
      ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($request->getRequestURI()));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'device'  => true,
        'title'   => $title,
      ));

  }

}

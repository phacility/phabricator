<?php

final class DrydockResourceListController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $title = pht('Resources');

    $resource_header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI('/drydock/resource/'), 'offset');
    $resources = id(new DrydockResourceQuery())
      ->executeWithOffsetPager($pager);

    $resource_list = $this->buildResourceListView($resources);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($request->getRequestURI()));

    $nav = $this->buildSideNav('resource');
    $nav->setCrumbs($crumbs);
    $nav->appendChild(
      array(
        $resource_header,
        $resource_list,
        $pager,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));

  }

}

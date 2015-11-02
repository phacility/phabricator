<?php

final class DrydockConsoleController extends DrydockController {

  public function shouldAllowPublic() {
    return true;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    // These are only used on mobile.

    $nav->addFilter('blueprint', pht('Blueprints'));
    $nav->addFilter('resource', pht('Resources'));
    $nav->addFilter('lease', pht('Leases'));
    $nav->addFilter('operation', pht('Repository Operations'));

    $nav->selectFilter(null);

    return $nav;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Blueprints'))
        ->setFontIcon('fa-map-o')
        ->setHref($this->getApplicationURI('blueprint/'))
        ->addAttribute(
          pht(
            'Configure blueprints so Drydock can build resources, like '.
            'hosts and working copies.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Resources'))
        ->setFontIcon('fa-map')
        ->setHref($this->getApplicationURI('resource/'))
        ->addAttribute(
          pht('View and manage resources Drydock has built, like hosts.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Leases'))
        ->setFontIcon('fa-link')
        ->setHref($this->getApplicationURI('lease/'))
        ->addAttribute(pht('Manage leases on resources.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Repository Operations'))
        ->setFontIcon('fa-fighter-jet')
        ->setHref($this->getApplicationURI('operation/'))
        ->addAttribute(pht('Review the repository operation queue.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Drydock Console'))
      ->setObjectList($menu);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title'  => pht('Drydock Console'),
      ));
  }

}

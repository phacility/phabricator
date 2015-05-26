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
    $nav->addFilter('log', pht('Logs'));

    $nav->selectFilter(null);

    return $nav;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Blueprints'))
        ->setHref($this->getApplicationURI('blueprint/'))
        ->addAttribute(
          pht(
            'Configure blueprints so Drydock can build resources, like '.
            'hosts and working copies.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Resources'))
        ->setHref($this->getApplicationURI('resource/'))
        ->addAttribute(
          pht('View and manage resources Drydock has built, like hosts.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Leases'))
        ->setHref($this->getApplicationURI('lease/'))
        ->addAttribute(pht('Manage leases on resources.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Logs'))
        ->setHref($this->getApplicationURI('log/'))
        ->addAttribute(pht('View logs.')));


    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $menu,
      ),
      array(
        'title'  => pht('Drydock Console'),
      ));
  }

}

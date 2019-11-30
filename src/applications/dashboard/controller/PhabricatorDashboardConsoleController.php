<?php

final class PhabricatorDashboardConsoleController
  extends PhabricatorDashboardController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setBig(true);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Portals'))
        ->setImageIcon('fa-compass')
        ->setHref('/portal/')
        ->setClickable(true)
        ->addAttribute(
          pht(
            'Portals are collections of dashboards, links, and other '.
            'resources that can provide a high-level overview of a '.
            'project.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Dashboards'))
        ->setImageIcon('fa-dashboard')
        ->setHref($this->getApplicationURI('/'))
        ->setClickable(true)
        ->addAttribute(
          pht(
            'Dashboards organize panels, creating a cohesive page for '.
            'analysis or action.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Panels'))
        ->setImageIcon('fa-line-chart')
        ->setHref($this->getApplicationURI('panel/'))
        ->setClickable(true)
        ->addAttribute(
          pht(
            'Panels show queries, charts, and other information to provide '.
            'insight on a particular topic.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));
    $crumbs->setBorder(true);

    $title = pht('Dashboard Console');

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setObjectList($menu);

    $launch_view = id(new PHUILauncherView())
      ->appendChild($box);

    $view = id(new PHUITwoColumnView())
      ->setFooter($launch_view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}

<?php

final class PhabricatorDashboardViewController
  extends PhabricatorDashboardProfileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needPanels(true)
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }
    $this->setDashboard($dashboard);

    $dashboard_uri = $this->getApplicationURI("view/{$id}/");
    $title = $dashboard->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('View'));

    if ($dashboard->getPanelPHIDs()) {
      $rendered_dashboard = id(new PhabricatorDashboardRenderingEngine())
        ->setViewer($viewer)
        ->setDashboard($dashboard)
        ->renderDashboard();
      $content = id(new PHUIBoxView())
        ->addClass('dashboard-preview-box')
        ->appendChild($rendered_dashboard);
    } else {
      $content = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(pht('This dashboard has no panels yet.'));
    }

    $navigation = $this->buildSideNavView('view');
    $header = $this->buildHeaderView();

    $install_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText('Install Dashboard')
      ->setIcon('fa-plus')
      ->setColor(PHUIButtonView::GREEN)
      ->setWorkflow(true)
      ->setHref($this->getApplicationURI("/install/{$id}/"));
    $header->addActionLink($install_button);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $content,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

}

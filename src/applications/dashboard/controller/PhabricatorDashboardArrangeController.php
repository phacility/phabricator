<?php

final class PhabricatorDashboardArrangeController
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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $dashboard,
      PhabricatorPolicyCapability::CAN_EDIT);

    $title = $dashboard->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Arrange'));
    $header = $this->buildHeaderView();

    $info_view = null;
    if (!$can_edit) {
      $no_edit = pht(
        'You do not have permission to edit this dashboard.');

      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setErrors(array($no_edit));
    }

    $rendered_dashboard = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard)
      ->setArrangeMode($can_edit)
      ->renderDashboard();

    $dashboard_box = id(new PHUIBoxView())
      ->addClass('dashboard-preview-box')
      ->appendChild($rendered_dashboard);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $info_view,
        $dashboard_box,
      ));

    $navigation = $this->buildSideNavView('arrange');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);

  }

}

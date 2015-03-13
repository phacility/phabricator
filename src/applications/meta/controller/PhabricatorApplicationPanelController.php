<?php

final class PhabricatorApplicationPanelController
  extends PhabricatorApplicationsController {

  private $application;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $application = $request->getURIData('application');
    $panel_key = $request->getURIData('panel');

    $selected = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$selected) {
      return new Aphront404Response();
    }

    $panels =
      PhabricatorApplicationConfigurationPanel::loadAllPanelsForApplication(
        $selected);
    if (empty($panels[$panel_key])) {
      return new Aphront404Response();
    }

    $panel = $panels[$panel_key];

    if (!$panel->shouldShowForApplication($selected)) {
      return new Aphront404Response();
    }

    $panel->setViewer($viewer);
    $panel->setApplication($selected);

    $this->application = $selected;

    return $panel->handlePanelRequest($request, $this);
  }

  public function buildPanelCrumbs(
    PhabricatorApplicationConfigurationPanel $panel) {
    $application = $this->application;

    $crumbs = $this->buildApplicationCrumbs();

    $view_uri = '/applications/view/'.get_class($application).'/';
    $crumbs->addTextCrumb($application->getName(), $view_uri);

    return $crumbs;
  }

  public function buildPanelPage(
    PhabricatorApplicationConfigurationPanel $panel,
    $content,
    array $options) {
    return $this->buildApplicationPage($content, $options);
  }

}

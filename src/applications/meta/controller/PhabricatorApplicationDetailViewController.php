<?php

final class PhabricatorApplicationDetailViewController
  extends PhabricatorApplicationsController {


  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $application = $request->getURIData('application');

    $selected = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->executeOne();
    if (!$selected) {
      return new Aphront404Response();
    }

    $title = $selected->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($selected->getName());
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($selected)
      ->setHeaderIcon($selected->getIcon());

    if ($selected->isInstalled()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Installed'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Uninstalled'));
    }

    $curtain = $this->buildCurtain($selected);
    $details = $this->buildPropertySectionView($selected);
    $policies = $this->buildPolicyView($selected);

    $configs =
      PhabricatorApplicationConfigurationPanel::loadAllPanelsForApplication(
        $selected);

    $panels = array();
    foreach ($configs as $config) {
      $config->setViewer($viewer);
      $config->setApplication($selected);
      $panel = $config->buildConfigurationPagePanel();
      $panel->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);
      $panels[] = $panel;
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $policies,
          $panels,
        ))
      ->addPropertySection(pht('Details'), $details);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildPropertySectionView(
    PhabricatorApplication $application) {

    $viewer = $this->getViewer();
    $properties = id(new PHUIPropertyListView());

    $properties->addProperty(
      pht('Description'),
      $application->getShortDescription());

    if ($application->getFlavorText()) {
      $properties->addProperty(
        null,
        phutil_tag('em', array(), $application->getFlavorText()));
    }

    if ($application->isPrototype()) {
      $proto_href = PhabricatorEnv::getDoclink(
        'User Guide: Prototype Applications');
      $learn_more = phutil_tag(
        'a',
        array(
          'href' => $proto_href,
          'target' => '_blank',
        ),
        pht('Learn More'));

      $properties->addProperty(
        pht('Prototype'),
        pht(
          'This application is a prototype. %s',
          $learn_more));
    }

    $overview = $application->getOverview();
    if (strlen($overview)) {
      $overview = new PHUIRemarkupView($viewer, $overview);
      $properties->addSectionHeader(
        pht('Overview'), PHUIPropertyListView::ICON_SUMMARY);
      $properties->addTextContent($overview);
    }

    return $properties;
  }

  private function buildPolicyView(
    PhabricatorApplication $application) {

    $viewer = $this->getViewer();
    $properties = id(new PHUIPropertyListView());

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Policies'));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $application);

    foreach ($application->getCapabilities() as $capability) {
      $properties->addProperty(
        $application->getCapabilityLabel($capability),
        idx($descriptions, $capability));
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);

  }

  private function buildCurtain(PhabricatorApplication $application) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $application,
      PhabricatorPolicyCapability::CAN_EDIT);

    $key = get_class($application);
    $edit_uri = $this->getApplicationURI("edit/{$key}/");
    $install_uri = $this->getApplicationURI("{$key}/install/");
    $uninstall_uri = $this->getApplicationURI("{$key}/uninstall/");

    $curtain = $this->newCurtainView($application);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Policies'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($edit_uri));

    if ($application->canUninstall()) {
      if ($application->isInstalled()) {
        $curtain->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Uninstall'))
            ->setIcon('fa-times')
            ->setDisabled(!$can_edit)
            ->setWorkflow(true)
            ->setHref($uninstall_uri));
      } else {
        $action = id(new PhabricatorActionView())
          ->setName(pht('Install'))
          ->setIcon('fa-plus')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true)
          ->setHref($install_uri);

        $prototypes_enabled = PhabricatorEnv::getEnvConfig(
          'phabricator.show-prototypes');
        if ($application->isPrototype() && !$prototypes_enabled) {
          $action->setDisabled(true);
        }

        $curtain->addAction($action);
      }
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Uninstall'))
          ->setIcon('fa-times')
          ->setWorkflow(true)
          ->setDisabled(true)
          ->setHref($uninstall_uri));
    }

    return $curtain;
  }

}

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

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($selected);

    if ($selected->isInstalled()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Installed'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Uninstalled'));
    }

    $actions = $this->buildActionView($viewer, $selected);
    $properties = $this->buildPropertyView($selected, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $configs =
      PhabricatorApplicationConfigurationPanel::loadAllPanelsForApplication(
        $selected);

    $panels = array();
    foreach ($configs as $config) {
      $config->setViewer($viewer);
      $config->setApplication($selected);

      $panels[] = $config->buildConfigurationPagePanel();
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $panels,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyView(
    PhabricatorApplication $application,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView());
    $properties->setActionList($actions);

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
    if ($overview) {
      $properties->addSectionHeader(
        pht('Overview'), PHUIPropertyListView::ICON_SUMMARY);
      $properties->addTextContent(
        PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())->setContent($overview),
          'default',
          $viewer));
    }

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $application);

    $properties->addSectionHeader(
      pht('Policies'), 'fa-lock');

    foreach ($application->getCapabilities() as $capability) {
      $properties->addProperty(
        $application->getCapabilityLabel($capability),
        idx($descriptions, $capability));
    }

    return $properties;
  }

  private function buildActionView(
    PhabricatorUser $user,
    PhabricatorApplication $selected) {

    $view = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $selected,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI('edit/'.get_class($selected).'/');

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Policies'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($edit_uri));

    if ($selected->canUninstall()) {
      if ($selected->isInstalled()) {
        $view->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Uninstall'))
            ->setIcon('fa-times')
            ->setDisabled(!$can_edit)
            ->setWorkflow(true)
            ->setHref(
              $this->getApplicationURI(get_class($selected).'/uninstall/')));
      } else {
        $action = id(new PhabricatorActionView())
          ->setName(pht('Install'))
          ->setIcon('fa-plus')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true)
          ->setHref(
             $this->getApplicationURI(get_class($selected).'/install/'));

        $prototypes_enabled = PhabricatorEnv::getEnvConfig(
          'phabricator.show-prototypes');
        if ($selected->isPrototype() && !$prototypes_enabled) {
          $action->setDisabled(true);
        }

        $view->addAction($action);
      }
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Uninstall'))
          ->setIcon('fa-times')
          ->setWorkflow(true)
          ->setDisabled(true)
          ->setHref(
            $this->getApplicationURI(get_class($selected).'/uninstall/')));
    }

    return $view;
  }

}

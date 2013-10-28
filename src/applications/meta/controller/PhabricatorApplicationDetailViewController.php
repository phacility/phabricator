<?php

final class PhabricatorApplicationDetailViewController
  extends PhabricatorApplicationsController{

  private $application;

  public function willProcessRequest(array $data) {
    $this->application = $data['application'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $selected = id(new PhabricatorApplicationQuery())
      ->setViewer($user)
      ->withClasses(array($this->application))
      ->executeOne();
    if (!$selected) {
      return new Aphront404Response();
    }

    $title = $selected->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($selected->getName()));

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($user)
      ->setPolicyObject($selected);

    if ($selected->isInstalled()) {
      $header->setStatus('oh-ok', null, pht('Installed'));
    } else {
      $header->setStatus('policy-noone', null, pht('Uninstalled'));
    }

    $actions = $this->buildActionView($user, $selected);
    $properties = $this->buildPropertyView($selected, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildPropertyView(
    PhabricatorApplication $application,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->addProperty(pht('Description'), $application->getShortDescription());
    $properties->setActionList($actions);

    if ($application->isBeta()) {
      $properties->addProperty(
        pht('Release'),
        pht('Beta'));
    }

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $application);

    $properties->addSectionHeader(pht('Policies'));

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
        ->setIcon('edit')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($edit_uri));

    if ($selected->canUninstall()) {
      if ($selected->isInstalled()) {
        $view->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Uninstall'))
            ->setIcon('delete')
            ->setDisabled(!$can_edit)
            ->setWorkflow(true)
            ->setHref(
              $this->getApplicationURI(get_class($selected).'/uninstall/')));
      } else {
        $action = id(new PhabricatorActionView())
          ->setName(pht('Install'))
          ->setIcon('new')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true)
          ->setHref(
             $this->getApplicationURI(get_class($selected).'/install/'));

        $beta_enabled = PhabricatorEnv::getEnvConfig(
          'phabricator.show-beta-applications');
        if ($selected->isBeta() && !$beta_enabled) {
          $action->setDisabled(true);
        }

        $view->addAction($action);
      }
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Uninstall'))
          ->setIcon('delete')
          ->setWorkflow(true)
          ->setDisabled(true)
          ->setHref(
            $this->getApplicationURI(get_class($selected).'/uninstall/')));
    }

    return $view;
  }

}

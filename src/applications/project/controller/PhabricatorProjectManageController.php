<?php

final class PhabricatorProjectManageController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $viewer = $request->getUser();
    $project = $this->getProject();
    $id = $project->getID();
    $picture = $project->getProfileImageURI();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Project History'))
      ->setUser($viewer)
      ->setPolicyObject($project)
      ->setImage($picture);

    if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ACTIVE) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }

    $curtain = $this->buildCurtain($project);
    $properties = $this->buildPropertyListView($project);

    $timeline = $this->buildTransactionTimeline(
      $project,
      new PhabricatorProjectTransactionQuery());
    $timeline->setShouldTerminate(true);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::PANEL_MANAGE);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $manage = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->setMainColumn(
        array(
          $timeline,
        ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(
        array(
          $project->getDisplayName(),
          pht('Manage'),
        ))
      ->appendChild(
        array(
          $manage,
        ));
  }

  private function buildCurtain(PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $id = $project->getID();
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($project);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Details'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Menu'))
        ->setIcon('fa-th-list')
        ->setHref($this->getApplicationURI("{$id}/panel/configure/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Picture'))
        ->setIcon('fa-picture-o')
        ->setHref($this->getApplicationURI("picture/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($project->isArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Project'))
          ->setIcon('fa-check')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Project'))
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $curtain;
  }

  private function buildPropertyListView(
    PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Looks Like'),
      $viewer->renderHandle($project->getPHID())->setAsTag(true));


    $field_list = PhabricatorCustomField::getObjectFields(
      $project,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($project, $viewer, $view);

    return $view;
  }


}

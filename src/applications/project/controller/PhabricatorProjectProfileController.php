<?php

final class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $id = $project->getID();
    $picture = $project->getProfileImageURI();

    $header = id(new PHUIHeaderView())
      ->setHeader($project->getName())
      ->setUser($viewer)
      ->setPolicyObject($project)
      ->setImage($picture);

    if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ACTIVE) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }

    $actions = $this->buildActionListView($project);
    $properties = $this->buildPropertyListView($project, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $project,
      new PhabricatorProjectTransactionQuery());
    $timeline->setShouldTerminate(true);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::PANEL_PROFILE);

    $crumbs = $this->buildApplicationCrumbs();

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle($project->getName())
      ->setPageObjectPHIDs(array($project->getPHID()))
      ->appendChild($object_box)
      ->appendChild($timeline);
  }

  private function buildActionListView(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $project->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($project);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Details'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Picture'))
        ->setIcon('fa-picture-o')
        ->setHref($this->getApplicationURI("picture/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($project->isArchived()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Project'))
          ->setIcon('fa-check')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Project'))
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $view;
  }

  private function buildPropertyListView(
    PhabricatorProject $project,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($project)
      ->setActionList($actions);

    $hashtags = array();
    foreach ($project->getSlugs() as $slug) {
      $hashtags[] = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_OBJECT)
        ->setName('#'.$slug->getSlug());
    }

    if ($hashtags) {
      $view->addProperty(pht('Hashtags'), phutil_implode_html(' ', $hashtags));
    }

    $view->addProperty(
      pht('Members'),
      $project->getMemberPHIDs()
        ? $viewer
          ->renderHandleList($project->getMemberPHIDs())
          ->setAsInline(true)
        : phutil_tag('em', array(), pht('None')));

    $view->addProperty(
      pht('Watchers'),
      $project->getWatcherPHIDs()
        ? $viewer
          ->renderHandleList($project->getWatcherPHIDs())
          ->setAsInline(true)
        : phutil_tag('em', array(), pht('None')));

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

<?php

final class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->needMembers(true)
      ->needWatchers(true)
      ->needImages(true)
      ->needSlugs(true);
    $id = $request->getURIData('id');
    $slug = $request->getURIData('slug');
    if ($slug) {
      $query->withSlugs(array($slug));
    } else {
      $query->withIDs(array($id));
    }
    $project = $query->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    if ($slug && $slug != $project->getPrimarySlug()) {
      return id(new AphrontRedirectResponse())
        ->setURI('/tag/'.$project->getPrimarySlug().'/');
    }

    $picture = $project->getProfileImageURI();

    $header = id(new PHUIHeaderView())
      ->setHeader($project->getName())
      ->setUser($user)
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

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("profile/{$id}/");
    $nav->appendChild($object_box);
    $nav->appendChild($timeline);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $project->getName(),
        'pageObjects' => array($project->getPHID()),
      ));
  }

  private function buildActionListView(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $project->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($project)
      ->setObjectURI($request->getRequestURI());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Details'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("details/{$id}/")));

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

    $action = null;
    if (!$project->isUserMember($viewer->getPHID())) {
      $can_join = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $project,
        PhabricatorPolicyCapability::CAN_JOIN);

      $action = id(new PhabricatorActionView())
        ->setUser($viewer)
        ->setRenderAsForm(true)
        ->setHref('/project/update/'.$project->getID().'/join/')
        ->setIcon('fa-plus')
        ->setDisabled(!$can_join)
        ->setName(pht('Join Project'));
      $view->addAction($action);
    } else {
      $action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/project/update/'.$project->getID().'/leave/')
        ->setIcon('fa-times')
        ->setName(pht('Leave Project...'));
      $view->addAction($action);

      if (!$project->isUserWatcher($viewer->getPHID())) {
        $action = id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/project/watch/'.$project->getID().'/')
          ->setIcon('fa-eye')
          ->setName(pht('Watch Project'));
        $view->addAction($action);
      } else {
        $action = id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setHref('/project/unwatch/'.$project->getID().'/')
          ->setIcon('fa-eye-slash')
          ->setName(pht('Unwatch Project'));
        $view->addAction($action);
      }
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

    $view->addProperty(pht('Hashtags'), phutil_implode_html(' ', $hashtags));

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

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $project);

    $view->addProperty(
      pht('Looks Like'),
      $viewer->renderHandle($project->getPHID())->setAsTag(true));

    $view->addProperty(
      pht('Joinable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_JOIN]);

    $field_list = PhabricatorCustomField::getObjectFields(
      $project,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($project, $viewer, $view);

    return $view;
  }


}

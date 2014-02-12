<?php

final class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  private $id;
  private $page;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->page = idx($data, 'page');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needMembers(true)
      ->needImages(true)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $picture = $project->getProfileImageURI();

    require_celerity_resource('phabricator-profile-css');

    $tasks = $this->renderTasksPage($project);

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $project->getPHID(),
      ));
    $query->setLimit(50);
    $query->setViewer($this->getRequest()->getUser());
    $stories = $query->execute();
    $feed = $this->renderStories($stories);

    $content = phutil_tag_div(
      'phabricator-project-layout',
      array($tasks, $feed));

    $header = id(new PHUIHeaderView())
      ->setHeader($project->getName())
      ->setUser($user)
      ->setPolicyObject($project)
      ->setImage($picture);

    if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ACTIVE) {
      $header->setStatus('oh-ok', '', pht('Active'));
    } else {
      $header->setStatus('policy-noone', '', pht('Archived'));
    }

    $actions = $this->buildActionListView($project);
    $properties = $this->buildPropertyListView($project, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($project->getName())
      ->setActionList($actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $content,
      ),
      array(
        'title' => $project->getName(),
        'device' => true,
      ));
  }

  private function renderFeedPage(PhabricatorProject $project) {

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(array($project->getPHID()));
    $query->setViewer($this->getRequest()->getUser());
    $query->setLimit(100);
    $stories = $query->execute();

    if (!$stories) {
      return pht('There are no stories about this project.');
    }

    return $this->renderStories($stories);
  }

  private function renderStories(array $stories) {
    assert_instances_of($stories, 'PhabricatorFeedStory');

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($this->getRequest()->getUser());
    $builder->setShowHovercards(true);
    $view = $builder->buildView();

    return phutil_tag_div(
      'profile-feed',
      $view->render());
  }


  private function renderTasksPage(PhabricatorProject $project) {

    $user = $this->getRequest()->getUser();

    $query = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withAnyProjects(array($project->getPHID()))
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setLimit(10);
    $tasks = $query->execute();

    $phids = mpull($tasks, 'getOwnerPHID');
    $phids = array_merge(
      $phids,
      array_mergev(mpull($tasks, 'getProjectPHIDs')));
    $phids = array_filter($phids);
    $handles = $this->loadViewerHandles($phids);

    $task_list = new ManiphestTaskListView();
    $task_list->setUser($user);
    $task_list->setTasks($tasks);
    $task_list->setHandles($handles);

    $content = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Open Tasks'))
      ->appendChild($task_list);

    return $content;
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
        ->setName(pht('Edit Project'))
        ->setIcon('edit')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($project->isArchived()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Unarchive Project'))
          ->setIcon('enable')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Project'))
          ->setIcon('disable')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Members'))
        ->setIcon('edit')
        ->setHref($this->getApplicationURI("members/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Picture'))
        ->setIcon('image')
        ->setHref($this->getApplicationURI("picture/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));


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
        ->setIcon('new')
        ->setDisabled(!$can_join)
        ->setName(pht('Join Project'));
    } else {
      $action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/project/update/'.$project->getID().'/leave/')
        ->setIcon('delete')
        ->setName(pht('Leave Project...'));
    }
    $view->addAction($action);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setHref($this->getApplicationURI("history/{$id}/"))
        ->setIcon('transcript'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Board (Beta)'))
        ->setHref($this->getApplicationURI("board/{$id}/"))
        ->setIcon('project'));

    return $view;
  }

  private function buildPropertyListView(
    PhabricatorProject $project,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->loadHandles($project->getMemberPHIDs());

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($project)
      ->setActionList($actions);

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($project->getDateCreated(), $viewer));

    $view->addProperty(
      pht('Members'),
      $project->getMemberPHIDs()
      ? $this->renderHandlesForPHIDs($project->getMemberPHIDs(), ',')
      : phutil_tag('em', array(), pht('None')));

    $field_list = PhabricatorCustomField::getObjectFields(
      $project,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($project, $viewer, $view);

    return $view;
  }


}

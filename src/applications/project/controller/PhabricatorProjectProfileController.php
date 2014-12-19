<?php

final class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  private $id;
  private $slug;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    // via /project/view/$id/
    $this->id = idx($data, 'id');
    // via /tag/$slug/
    $this->slug = idx($data, 'slug');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->needMembers(true)
      ->needWatchers(true)
      ->needImages(true)
      ->needSlugs(true);
    if ($this->slug) {
      $query->withSlugs(array($this->slug));
    } else {
      $query->withIDs(array($this->id));
    }
    $project = $query->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    if ($this->slug && $this->slug != $project->getPrimarySlug()) {
      return id(new AphrontRedirectResponse())
        ->setURI('/tag/'.$project->getPrimarySlug().'/');
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

    $id = $project->getID();
    $icon = id(new PHUIIconView())
          ->setIconFont('fa-columns');
    $board_btn = id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Workboard'))
        ->setHref($this->getApplicationURI("board/{$id}/"))
        ->setIcon($icon);

    $header = id(new PHUIHeaderView())
      ->setHeader($project->getName())
      ->setUser($user)
      ->setPolicyObject($project)
      ->setImage($picture)
      ->addActionLink($board_btn);

    if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ACTIVE) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Archived'));
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
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->needProjectPHIDs(true)
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

    $phid = $project->getPHID();
    $view_uri = urisprintf(
      '/maniphest/?statuses=%s&allProjects=%s#R',
      implode(',', ManiphestTaskStatus::getOpenStatusConstants()),
      $phid);
    $create_uri = '/maniphest/task/create/?projects='.$phid;
    $icon = id(new PHUIIconView())
      ->setIconFont('fa-list');
    $button_view = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View All'))
      ->setHref($view_uri)
      ->setIcon($icon);
    $icon_new = id(new PHUIIconView())
      ->setIconFont('fa-plus');
    $button_add = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('New Task'))
      ->setHref($create_uri)
      ->setIcon($icon_new);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Open Tasks'))
      ->addActionLink($button_add)
      ->addActionLink($button_view);

    $content = id(new PHUIObjectBoxView())
      ->setHeader($header)
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
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/")));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Members'))
        ->setIcon('fa-users')
        ->setHref($this->getApplicationURI("members/{$id}/"))
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

    $have_phriction = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorPhrictionApplication',
      $viewer);
    if ($have_phriction) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-book grey')
          ->setName(pht('View Wiki'))
          ->setWorkflow(true)
          ->setHref('/project/wiki/'));
    }

    return $view;
  }

  private function buildPropertyListView(
    PhabricatorProject $project,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->loadHandles(
      array_merge(
        $project->getMemberPHIDs(),
        $project->getWatcherPHIDs()));

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
        ? $this->renderHandlesForPHIDs($project->getMemberPHIDs(), ',')
        : phutil_tag('em', array(), pht('None')));

    $view->addProperty(
      pht('Watchers'),
      $project->getWatcherPHIDs()
        ? $this->renderHandlesForPHIDs($project->getWatcherPHIDs(), ',')
        : phutil_tag('em', array(), pht('None')));

    $field_list = PhabricatorCustomField::getObjectFields(
      $project,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($project, $viewer, $view);

    return $view;
  }


}

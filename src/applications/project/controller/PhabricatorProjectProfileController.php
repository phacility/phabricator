<?php

final class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  private $id;
  private $page;
  private $project;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->page = idx($data, 'page');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needMembers(true);

    $project = $query->executeOne();
    $this->project = $project;
    if (!$project) {
      return new Aphront404Response();
    }

    $profile = $project->loadProfile();
    if (!$profile) {
      $profile = new PhabricatorProjectProfile();
    }

    $picture = $profile->loadProfileImageURI();

    require_celerity_resource('phabricator-profile-css');

    $tasks = $this->renderTasksPage($project, $profile);

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $project->getPHID(),
      ));
    $query->setLimit(50);
    $query->setViewer($this->getRequest()->getUser());
    $stories = $query->execute();
    $feed = $this->renderStories($stories);
    $people = $this->renderPeoplePage($project, $profile);

    $content = id(new AphrontMultiColumnView())
      ->addColumn($people)
      ->addColumn($feed)
      ->setFluidLayout(true);

    $content = hsprintf(
      '<div class="phabricator-project-layout">%s%s</div>',
        $tasks,
        $content);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($project->getName())
      ->setSubheader(phutil_utf8_shorten($profile->getBlurb(), 1024))
      ->setImage($picture);

    $actions = $this->buildActionListView($project);
    $properties = $this->buildPropertyListView($project);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($project->getName()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $content,
      ),
      array(
        'title' => $project->getName(),
        'device' => true,
      ));
  }

  private function renderPeoplePage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $member_phids = $project->getMemberPHIDs();
    $handles = $this->loadViewerHandles($member_phids);

    $affiliated = array();
    foreach ($handles as $phids => $handle) {
      $affiliated[] = phutil_tag('li', array(), $handle->renderLink());
    }

    if ($affiliated) {
      $affiliated = phutil_tag('ul', array(), $affiliated);
    } else {
      $affiliated = hsprintf('<p><em>%s</em></p>', pht(
        'No one is affiliated with this project.'));
    }

    return hsprintf(
      '<div class="phabricator-profile-info-group profile-wrap-responsive">'.
        '<h1 class="phabricator-profile-info-header">%s</h1>'.
        '<div class="phabricator-profile-info-pane">%s</div>'.
      '</div>',
      pht('People'),
      $affiliated);
  }

  private function renderFeedPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

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

    return hsprintf(
      '<div class="profile-feed profile-wrap-responsive">'.
        '%s'.
      '</div>',
      $view->render());
  }


  private function renderTasksPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $user = $this->getRequest()->getUser();

    $query = id(new ManiphestTaskQuery())
      ->withAnyProjects(array($project->getPHID()))
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setLimit(10)
      ->setCalculateRows(true);
    $tasks = $query->execute();
    $count = $query->getRowCount();

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

    $open = number_format($count);

    $more_link = phutil_tag(
      'a',
      array(
        'href' => '/maniphest/view/all/?projects='.$project->getPHID(),
      ),
      pht("View All Open Tasks \xC2\xBB"));

    $content = hsprintf(
      '<div class="phabricator-profile-info-group profile-wrap-responsive">
        <h1 class="phabricator-profile-info-header">%s</h1>'.
        '<div class="phabricator-profile-info-pane">'.
          '%s'.
          '<div class="phabricator-profile-info-pane-more-link">%s</div>'.
        '</div>
      </div>',
      pht('Open Tasks (%s)', $open),
      $task_list,
      $more_link);

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

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Members'))
        ->setIcon('edit')
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

    return $view;
  }

  private function buildPropertyListView(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer)
      ->setObject($project);

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($project->getDateCreated(), $viewer));

    return $view;
  }


}

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
      ->needProfiles(true)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $profile = $project->getProfile();
    $picture = $profile->getProfileImageURI();

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

    $content = phutil_tag_div(
      'phabricator-project-layout',
      array($tasks, $content));

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
    $properties = $this->buildPropertyListView($project, $profile, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($project->getName()))
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
      $affiliated = phutil_tag('p', array(),
        phutil_tag('em', array(),
          pht('No one is affiliated with this project.')));
    }

    return phutil_tag_div(
      'phabricator-profile-info-group profile-wrap-responsive',
      array(
        phutil_tag(
          'h1',
          array('class' => 'phabricator-profile-info-header'),
          pht('People')),
        phutil_tag_div('phabricator-profile-info-pane', $affiliated),
      ));
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

    return phutil_tag_div(
      'profile-feed profile-wrap-responsive',
      $view->render());
  }


  private function renderTasksPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

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

    $list = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_LARGE)
      ->appendChild($task_list);

    $content = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Open Tasks'))
      ->appendChild($list);

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

    return $view;
  }

  private function buildPropertyListView(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($project)
      ->setActionList($actions);

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($project->getDateCreated(), $viewer));

    $view->addSectionHeader(pht('Description'));
    $view->addTextContent(
      PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($profile->getBlurb()),
        'default',
        $viewer));

    return $view;
  }


}

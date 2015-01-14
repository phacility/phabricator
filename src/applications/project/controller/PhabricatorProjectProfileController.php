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
    require_celerity_resource('phabricator-profile-css');
    $tasks = $this->renderTasksPage($project);
    $content = phutil_tag_div('phabricator-project-layout', $tasks);

    $phid = $project->getPHID();
    $create_uri = '/maniphest/task/create/?projects='.$phid;
    $icon_new = id(new PHUIIconView())
      ->setIconFont('fa-plus');
    $button_add = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('New Task'))
      ->setHref($create_uri)
      ->setIcon($icon_new);

    $header = id(new PHUIHeaderView())
      ->setHeader($project->getName())
      ->setUser($user)
      ->setPolicyObject($project)
      ->setImage($picture)
      ->addActionLink($button_add);

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

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("profile/{$id}/");
    $nav->appendChild($object_box);
    $nav->appendChild($content);

    return $this->buildApplicationPage(
      array(
        $nav,
      ),
      array(
        'title' => $project->getName(),
      ));
  }

  private function renderTasksPage(PhabricatorProject $project) {

    $user = $this->getRequest()->getUser();
    $limit = 50;

    $query = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withAnyProjects(array($project->getPHID()))
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->needProjectPHIDs(true)
      ->setLimit(($limit + 1));
    $tasks = $query->execute();
    $count = count($tasks);
    if ($count == ($limit + 1)) {
      array_pop($tasks);
    }

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
    $task_list->setNoDataString(pht('This project has no open tasks.'));

    $phid = $project->getPHID();
    $view_uri = urisprintf(
      '/maniphest/?statuses=%s&allProjects=%s#R',
      implode(',', ManiphestTaskStatus::getOpenStatusConstants()),
      $phid);
    $icon = id(new PHUIIconView())
      ->setIconFont('fa-search');
    $button_view = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Query'))
      ->setHref($view_uri)
      ->setIcon($icon);

      $header = id(new PHUIHeaderView())
        ->addActionLink($button_view);

    if ($count > $limit) {
        $header->setHeader(pht('Highest Priority (some)'));
    } else {
        $header->setHeader(pht('Highest Priority (all)'));
    }

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

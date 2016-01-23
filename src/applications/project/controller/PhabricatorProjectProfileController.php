<?php

final class PhabricatorProjectProfileController
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

    $member_list = id(new PhabricatorProjectMemberListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setLimit(5)
      ->setUserPHIDs($project->getMemberPHIDs());

    $watcher_list = id(new PhabricatorProjectWatcherListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setLimit(5)
      ->setUserPHIDs($project->getWatcherPHIDs());

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::PANEL_PROFILE);

    $watch_action = $this->renderWatchAction($project);

    $stories = id(new PhabricatorFeedQuery())
      ->setViewer($viewer)
      ->setFilterPHIDs(
        array(
          $project->getPHID(),
        ))
      ->setLimit(50)
      ->execute();


    $feed = $this->renderStories($stories);

    $feed_header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Activity'))
      ->addActionLink($watch_action);

    $feed = id(new PHUIObjectBoxView())
      ->setHeader($feed_header)
      ->appendChild($feed);

    $columns = id(new AphrontMultiColumnView())
      ->setFluidLayout(true)
      ->addColumn($feed)
      ->addColumn(
        array(
          $member_list,
          $watcher_list,
        ));

    $crumbs = $this->buildApplicationCrumbs();

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle($project->getName())
      ->setPageObjectPHIDs(array($project->getPHID()))
      ->appendChild(
        array(
          $object_box,
          $columns,
        ));
  }

  private function buildActionListView(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $project->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($project);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Project'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("history/{$id}/")));

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

    $view->addProperty(
      pht('Looks Like'),
      $viewer->renderHandle($project->getPHID())->setAsTag(true));

    $field_list = PhabricatorCustomField::getObjectFields(
      $project,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($project, $viewer, $view);

    return $view;
  }

  private function renderStories(array $stories) {
    assert_instances_of($stories, 'PhabricatorFeedStory');

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($this->getRequest()->getUser());
    $builder->setShowHovercards(true);
    $view = $builder->buildView();

    return phutil_tag_div('profile-feed', $view->render());
  }

  private function renderWatchAction(PhabricatorProject $project) {
    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();
    $id = $project->getID();

    $is_watcher = ($viewer_phid && $project->isUserWatcher($viewer_phid));

    if (!$is_watcher) {
      $watch_icon = 'fa-eye';
      $watch_text = pht('Watch Project');
      $watch_href = "/project/watch/{$id}/?via=profile";
    } else {
      $watch_icon = 'fa-eye-slash';
      $watch_text = pht('Unwatch Project');
      $watch_href = "/project/unwatch/{$id}/?via=profile";
    }

    $watch_icon = id(new PHUIIconView())
      ->setIconFont($watch_icon);

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon($watch_icon)
      ->setText($watch_text)
      ->setHref($watch_href);
  }


}

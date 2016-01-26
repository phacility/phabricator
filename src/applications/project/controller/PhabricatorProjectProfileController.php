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
      ->setImage($picture)
      ->setProfileHeader(true);

    if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ACTIVE) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $header->setImageEditURL($this->getApplicationURI("picture/{$id}/"));
    }

    $properties = $this->buildPropertyListView($project);

    $watch_action = $this->renderWatchAction($project);
    $header->addActionLink($watch_action);

    $member_list = id(new PhabricatorProjectMemberListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setLimit(5)
      ->setBackground(PHUIBoxView::GREY)
      ->setUserPHIDs($project->getMemberPHIDs());

    $watcher_list = id(new PhabricatorProjectWatcherListView())
      ->setUser($viewer)
      ->setProject($project)
      ->setLimit(5)
      ->setBackground(PHUIBoxView::GREY)
      ->setUserPHIDs($project->getWatcherPHIDs());

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::PANEL_PROFILE);

    $stories = id(new PhabricatorFeedQuery())
      ->setViewer($viewer)
      ->setFilterPHIDs(
        array(
          $project->getPHID(),
        ))
      ->setLimit(50)
      ->execute();

    $feed = $this->renderStories($stories);
    $feed = phutil_tag_div('project-view-feed', $feed);

    $columns = id(new PHUITwoColumnView())
      ->setMainColumn(
        array(
          $properties,
          $feed,
        ))
      ->setSideColumn(
        array(
          $member_list,
          $watcher_list,
        ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    require_celerity_resource('project-view-css');
    $home = phutil_tag(
      'div',
      array(
        'class' => 'project-view-home',
      ),
      array(
        $header,
        $columns,
      ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle($project->getName())
      ->setPageObjectPHIDs(array($project->getPHID()))
      ->appendChild(
        array(
          $home,
        ));
  }

  private function buildPropertyListView(
    PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($project);

    $field_list = PhabricatorCustomField::getObjectFields(
      $project,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($project, $viewer, $view);

    if ($view->isEmpty()) {
      return null;
    }

    $view = id(new PHUIBoxView())
      ->setColor(PHUIBoxView::GREY)
      ->appendChild($view)
      ->addClass('project-view-properties');

    return $view;
  }

  private function renderStories(array $stories) {
    assert_instances_of($stories, 'PhabricatorFeedStory');

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($this->getRequest()->getUser());
    $builder->setShowHovercards(true);
    $view = $builder->buildView();

    return $view;
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

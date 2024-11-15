<?php

final class PhabricatorRoleProfileController
  extends PhabricatorRoleController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadRole();
    if ($response) {
      return $response;
    }

    $viewer = $request->getUser();
    $role = $this->getRole();
    $id = $role->getID();
    $picture = $role->getProfileImageURI();
    $icon = $role->getDisplayIconIcon();
    $icon_name = $role->getDisplayIconName();
    $tag = id(new PHUITagView())
      ->setIcon($icon)
      ->setName($icon_name)
      ->addClass('project-view-header-tag')
      ->setType(PHUITagView::TYPE_SHADE);

    $header = id(new PHUIHeaderView())
      ->setHeader(array($role->getDisplayName(), $tag))
      ->setUser($viewer)
      ->setPolicyObject($role)
      ->setProfileHeader(true);

    if ($role->getStatus() == PhabricatorRoleStatus::STATUS_ACTIVE) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $header->setImageEditURL($this->getApplicationURI("picture/{$id}/"));
    }

    $properties = $this->buildPropertyListView($role);

    $watch_action = $this->renderWatchAction($role);
    $header->addActionLink($watch_action);

    $subtype = $role->newSubtypeObject();
    if ($subtype && $subtype->hasTagView()) {
      $subtype_tag = $subtype->newTagView();
      $header->addTag($subtype_tag);
    }

    $milestone_list = $this->buildMilestoneList($role);
    $subrole_list = $this->buildSubroleList($role);

    $member_list = id(new PhabricatorRoleMemberListView())
      ->setUser($viewer)
      ->setRole($role)
      ->setLimit(10)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setUserPHIDs($role->getMemberPHIDs());

    $watcher_list = id(new PhabricatorRoleWatcherListView())
      ->setUser($viewer)
      ->setRole($role)
      ->setLimit(10)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setUserPHIDs($role->getWatcherPHIDs());

    $nav = $this->newNavigation(
      $role,
      PhabricatorRole::ITEM_PROFILE);

    $query = id(new PhabricatorFeedQuery())
      ->setViewer($viewer)
      ->withFilterPHIDs(array($role->getPHID()))
      ->setLimit(50)
      ->setReturnPartialResultsOnOverheat(true);

    $stories = $query->execute();

    $overheated_view = null;
    $is_overheated = $query->getIsOverheated();
    if ($is_overheated) {
      $overheated_message =
        PhabricatorApplicationSearchController::newOverheatedError(
          (bool)$stories);

      $overheated_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setTitle(pht('Query Overheated'))
        ->setErrors(
          array(
            $overheated_message,
          ));
    }

    $view_all = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon(
        id(new PHUIIconView())
          ->setIcon('fa-list-ul'))
      ->setText(pht('View All'))
      ->setHref('/feed/?rolePHIDs='.$role->getPHID());

    $feed_header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Activity'))
      ->addActionLink($view_all);

    $feed = $this->renderStories($stories);
    $feed = id(new PHUIObjectBoxView())
      ->setHeader($feed_header)
      ->addClass('project-view-feed')
      ->appendChild(
        array(
          $overheated_view,
          $feed,
        ));

    require_celerity_resource('project-view-css');

    $home = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setMainColumn(
        array(
          $properties,
          $feed,
        ))
      ->setSideColumn(
        array(
          $milestone_list,
          $subrole_list,
          $member_list,
          $watcher_list,
        ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle($role->getDisplayName())
      ->setPageObjectPHIDs(array($role->getPHID()))
      ->appendChild($home);
  }

  private function buildPropertyListView(
    PhabricatorRole $role) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($role);

    $field_list = PhabricatorCustomField::getObjectFields(
      $role,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($role, $viewer, $view);

    if (!$view->hasAnyProperties()) {
      return null;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Details'));

    $view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($view)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
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

  private function renderWatchAction(PhabricatorRole $role) {
    $viewer = $this->getViewer();
    $id = $role->getID();

    if (!$viewer->isLoggedIn()) {
      $is_watcher = false;
      $is_ancestor = false;
    } else {
      $viewer_phid = $viewer->getPHID();
      $is_watcher = $role->isUserWatcher($viewer_phid);
      $is_ancestor = $role->isUserAncestorWatcher($viewer_phid);
    }

    if ($is_ancestor && !$is_watcher) {
      $watch_icon = 'fa-eye';
      $watch_text = pht('Watching Ancestor');
      $watch_href = "/role/watch/{$id}/?via=profile";
      $watch_disabled = true;
    } else if (!$is_watcher) {
      $watch_icon = 'fa-eye';
      $watch_text = pht('Watch Role');
      $watch_href = "/role/watch/{$id}/?via=profile";
      $watch_disabled = false;
    } else {
      $watch_icon = 'fa-eye-slash';
      $watch_text = pht('Unwatch Role');
      $watch_href = "/role/unwatch/{$id}/?via=profile";
      $watch_disabled = false;
    }

    $watch_icon = id(new PHUIIconView())
      ->setIcon($watch_icon);

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon($watch_icon)
      ->setText($watch_text)
      ->setHref($watch_href)
      ->setDisabled($watch_disabled);
  }

  private function buildMilestoneList(PhabricatorRole $role) {
    if (!$role->getHasMilestones()) {
      return null;
    }

    $viewer = $this->getViewer();
    $id = $role->getID();

    $milestones = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withParentRolePHIDs(array($role->getPHID()))
      ->needImages(true)
      ->withIsMilestone(true)
      ->withStatuses(
        array(
          PhabricatorRoleStatus::STATUS_ACTIVE,
        ))
      ->setOrderVector(array('milestoneNumber', 'id'))
      ->execute();
    if (!$milestones) {
      return null;
    }

    $milestone_list = id(new PhabricatorRoleListView())
      ->setUser($viewer)
      ->setRoles($milestones)
      ->renderList();

    $view_all = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon(
        id(new PHUIIconView())
          ->setIcon('fa-list-ul'))
      ->setText(pht('View All'))
      ->setHref("/role/subroles/{$id}/");

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Milestones'))
      ->addActionLink($view_all);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($milestone_list);
  }

  private function buildSubroleList(PhabricatorRole $role) {
    if (!$role->getHasSubroles()) {
      return null;
    }

    $viewer = $this->getViewer();
    $id = $role->getID();

    $limit = 25;

    $subroles = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withParentRolePHIDs(array($role->getPHID()))
      ->needImages(true)
      ->withStatuses(
        array(
          PhabricatorRoleStatus::STATUS_ACTIVE,
        ))
      ->withIsMilestone(false)
      ->setLimit($limit)
      ->execute();
    if (!$subroles) {
      return null;
    }

    $subrole_list = id(new PhabricatorRoleListView())
      ->setUser($viewer)
      ->setRoles($subroles)
      ->renderList();

    $view_all = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon(
        id(new PHUIIconView())
          ->setIcon('fa-list-ul'))
      ->setText(pht('View All'))
      ->setHref("/role/subroles/{$id}/");

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subroles'))
      ->addActionLink($view_all);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($subrole_list);
  }

}

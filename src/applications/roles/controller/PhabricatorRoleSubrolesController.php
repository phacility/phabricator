<?php

final class PhabricatorRoleSubrolesController
  extends PhabricatorRoleController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadRole();
    if ($response) {
      return $response;
    }

    $role = $this->getRole();
    $id = $role->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);

    $allows_subroles = $role->supportsSubroles();
    $allows_milestones = $role->supportsMilestones();

    $subrole_list = null;
    $milestone_list = null;

    if ($allows_subroles) {
      $subroles = id(new PhabricatorRoleQuery())
        ->setViewer($viewer)
        ->withParentRolePHIDs(array($role->getPHID()))
        ->needImages(true)
        ->withIsMilestone(false)
        ->execute();

      $subrole_list = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('%s Subroles', $role->getName()))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setObjectList(
          id(new PhabricatorRoleListView())
            ->setUser($viewer)
            ->setRoles($subroles)
            ->setNoDataString(pht('This role has no subroles.'))
            ->renderList());
    } else {
      $subroles = array();
    }

    if ($allows_milestones) {
      $milestones = id(new PhabricatorRoleQuery())
        ->setViewer($viewer)
        ->withParentRolePHIDs(array($role->getPHID()))
        ->needImages(true)
        ->withIsMilestone(true)
        ->setOrderVector(array('milestoneNumber', 'id'))
        ->execute();

      $milestone_list = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('%s Milestones', $role->getName()))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setObjectList(
          id(new PhabricatorRoleListView())
            ->setUser($viewer)
            ->setRoles($milestones)
            ->setNoDataString(pht('This role has no milestones.'))
            ->renderList());
    } else {
      $milestones = array();
    }

    $curtain = $this->buildCurtainView(
      $role,
      $milestones,
      $subroles);

    $nav = $this->newNavigation(
      $role,
      PhabricatorRole::ITEM_SUBROLES);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Subroles'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subroles and Milestones'))
      ->setHeaderIcon('fa-sitemap');

    require_celerity_resource('project-view-css');

    // This page isn't reachable via UI, but make it pretty anyways.
    $info_view = null;
    if (!$milestone_list && !$subrole_list) {
      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(pht('Milestone roles do not support subroles '.
          'or milestones.'));
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setMainColumn(array(
          $info_view,
          $subrole_list,
          $milestone_list,
      ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($role->getName(), pht('Subroles')))
      ->appendChild($view);
  }

  private function buildCurtainView(
    PhabricatorRole $role,
    array $milestones,
    array $subroles) {
    $viewer = $this->getViewer();
    $id = $role->getID();

    $can_create = $this->hasApplicationCapability(
      RoleCreateRolesCapability::CAPABILITY);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);

    $allows_subroles = $role->supportsSubroles();
    $allows_milestones = $role->supportsMilestones();

    $curtain = $this->newCurtainView();

    $can_subrole = ($can_create && $can_edit && $allows_subroles);

    // If we're offering to create the first subrole, we're going to warn
    // the user about the effects before moving forward.
    if ($can_subrole && !$subroles) {
      $subrole_href = "/role/warning/{$id}/";
      $subrole_disabled = false;
      $subrole_workflow = true;
    } else {
      $subrole_href = "/role/edit/?parent={$id}";
      $subrole_disabled = !$can_subrole;
      $subrole_workflow = !$can_subrole;
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Create Subrole'))
        ->setIcon('fa-plus')
        ->setHref($subrole_href)
        ->setDisabled($subrole_disabled)
        ->setWorkflow($subrole_workflow));

    if ($allows_milestones && $milestones) {
      $milestone_text = pht('Create Next Milestone');
    } else {
      $milestone_text = pht('Create Milestone');
    }

    $can_milestone = ($can_create && $can_edit && $allows_milestones);
    $milestone_href = "/role/edit/?milestone={$id}";

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($milestone_text)
        ->setIcon('fa-plus')
        ->setHref($milestone_href)
        ->setDisabled(!$can_milestone)
        ->setWorkflow(!$can_milestone));

    if (!$role->supportsSubroles()) {
      $note = pht(
        'This role is a milestone, and milestones may not have '.
        'subroles.');
    } else {
      if (!$subroles) {
        $note = pht('Subroles can be created for this role.');
      } else {
        $note = pht('This role has subroles.');
      }
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Subroles'))
      ->appendChild($note);

    if (!$role->supportsSubroles()) {
      $note = pht(
        'This role is already a milestone, and milestones may not '.
        'have their own milestones.');
    } else {
      if (!$milestones) {
        $note = pht('Milestones can be created for this role.');
      } else {
        $note = pht('This role has milestones.');
      }
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Milestones'))
      ->appendChild($note);

    return $curtain;
  }

  private function renderStatus($icon, $target, $note) {
    $item = id(new PHUIStatusItemView())
      ->setIcon($icon)
      ->setTarget(phutil_tag('strong', array(), $target))
      ->setNote($note);

    return id(new PHUIStatusListView())
      ->addItem($item);
  }



}

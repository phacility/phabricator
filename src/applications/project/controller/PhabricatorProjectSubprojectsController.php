<?php

final class PhabricatorProjectSubprojectsController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $id = $project->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $allows_subprojects = $project->supportsSubprojects();
    $allows_milestones = $project->supportsMilestones();

    $subproject_list = null;
    $milestone_list = null;

    if ($allows_subprojects) {
      $subprojects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withParentProjectPHIDs(array($project->getPHID()))
        ->needImages(true)
        ->withIsMilestone(false)
        ->execute();

      $subproject_list = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('%s Subprojects', $project->getName()))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setObjectList(
          id(new PhabricatorProjectListView())
            ->setUser($viewer)
            ->setProjects($subprojects)
            ->setNoDataString(pht('This project has no subprojects.'))
            ->renderList());
    } else {
      $subprojects = array();
    }

    if ($allows_milestones) {
      $milestones = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withParentProjectPHIDs(array($project->getPHID()))
        ->needImages(true)
        ->withIsMilestone(true)
        ->setOrderVector(array('milestoneNumber', 'id'))
        ->execute();

      $milestone_list = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('%s Milestones', $project->getName()))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setObjectList(
          id(new PhabricatorProjectListView())
            ->setUser($viewer)
            ->setProjects($milestones)
            ->setNoDataString(pht('This project has no milestones.'))
            ->renderList());
    } else {
      $milestones = array();
    }

    $curtain = $this->buildCurtainView(
      $project,
      $milestones,
      $subprojects);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::ITEM_SUBPROJECTS);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Subprojects'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subprojects and Milestones'))
      ->setHeaderIcon('fa-sitemap');

    require_celerity_resource('project-view-css');

    // This page isn't reachable via UI, but make it pretty anyways.
    $info_view = null;
    if (!$milestone_list && !$subproject_list) {
      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(pht('Milestone projects do not support subprojects '.
          'or milestones.'));
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setMainColumn(array(
          $info_view,
          $milestone_list,
          $subproject_list,
      ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), pht('Subprojects')))
      ->appendChild($view);
  }

  private function buildCurtainView(
    PhabricatorProject $project,
    array $milestones,
    array $subprojects) {
    $viewer = $this->getViewer();
    $id = $project->getID();

    $can_create = $this->hasApplicationCapability(
      ProjectCreateProjectsCapability::CAPABILITY);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $allows_milestones = $project->supportsMilestones();
    $allows_subprojects = $project->supportsSubprojects();

    $curtain = $this->newCurtainView();

    if ($allows_milestones && $milestones) {
      $milestone_text = pht('Create Next Milestone');
    } else {
      $milestone_text = pht('Create Milestone');
    }

    $can_milestone = ($can_create && $can_edit && $allows_milestones);
    $milestone_href = "/project/edit/?milestone={$id}";

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($milestone_text)
        ->setIcon('fa-plus')
        ->setHref($milestone_href)
        ->setDisabled(!$can_milestone)
        ->setWorkflow(!$can_milestone));

    $can_subproject = ($can_create && $can_edit && $allows_subprojects);

    // If we're offering to create the first subproject, we're going to warn
    // the user about the effects before moving forward.
    if ($can_subproject && !$subprojects) {
      $subproject_href = "/project/warning/{$id}/";
      $subproject_disabled = false;
      $subproject_workflow = true;
    } else {
      $subproject_href = "/project/edit/?parent={$id}";
      $subproject_disabled = !$can_subproject;
      $subproject_workflow = !$can_subproject;
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Create Subproject'))
        ->setIcon('fa-plus')
        ->setHref($subproject_href)
        ->setDisabled($subproject_disabled)
        ->setWorkflow($subproject_workflow));


    if (!$project->supportsMilestones()) {
      $note = pht(
        'This project is already a milestone, and milestones may not '.
        'have their own milestones.');
    } else {
      if (!$milestones) {
        $note = pht('Milestones can be created for this project.');
      } else {
        $note = pht('This project has milestones.');
      }
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Milestones'))
      ->appendChild($note);

    if (!$project->supportsSubprojects()) {
      $note = pht(
        'This project is a milestone, and milestones may not have '.
        'subprojects.');
    } else {
      if (!$subprojects) {
        $note = pht('Subprojects can be created for this project.');
      } else {
        $note = pht('This project has subprojects.');
      }
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Subprojects'))
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

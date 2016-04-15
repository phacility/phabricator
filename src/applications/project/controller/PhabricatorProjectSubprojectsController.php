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

    if ($allows_subprojects) {
      $subprojects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withParentProjectPHIDs(array($project->getPHID()))
        ->needImages(true)
        ->withIsMilestone(false)
        ->execute();
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
    } else {
      $milestones = array();
    }

    if ($milestones) {
      $milestone_list = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Milestones'))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setObjectList(
          id(new PhabricatorProjectListView())
            ->setUser($viewer)
            ->setProjects($milestones)
            ->renderList());
    } else {
      $milestone_list = null;
    }

    if ($subprojects) {
      $subproject_list = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Subprojects'))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setObjectList(
          id(new PhabricatorProjectListView())
            ->setUser($viewer)
            ->setProjects($subprojects)
            ->renderList());
    } else {
      $subproject_list = null;
    }

    $property_list = $this->buildPropertyList(
      $project,
      $milestones,
      $subprojects);

    $curtain = $this->buildCurtainView(
      $project,
      $milestones,
      $subprojects);


    $details = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($property_list);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::PANEL_SUBPROJECTS);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Subprojects'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subprojects and Milestones'))
      ->setHeaderIcon('fa-sitemap');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $details,
          $milestone_list,
          $subproject_list,
      ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), pht('Subprojects')))
      ->appendChild($view);
  }

  private function buildPropertyList(
    PhabricatorProject $project,
    array $milestones,
    array $subprojects) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Prototype'),
      $this->renderStatus(
        'fa-exclamation-triangle red',
        pht('Warning'),
        pht('Subprojects and milestones are only partially implemented.')));

    if (!$project->supportsMilestones()) {
      $milestone_status = $this->renderStatus(
        'fa-times grey',
        pht('Already Milestone'),
        pht(
          'This project is already a milestone, and milestones may not '.
          'have their own milestones.'));
    } else {
      if (!$milestones) {
        $milestone_status = $this->renderStatus(
          'fa-check grey',
          pht('None Created'),
          pht(
            'You can create milestones for this project.'));
      } else {
        $milestone_status = $this->renderStatus(
          'fa-check green',
          pht('Has Milestones'),
          pht('This project has milestones.'));
      }
    }

    $view->addProperty(pht('Milestones'), $milestone_status);

    if (!$project->supportsSubprojects()) {
      $subproject_status = $this->renderStatus(
        'fa-times grey',
        pht('Milestone'),
        pht(
          'This project is a milestone, and milestones may not have '.
          'subprojects.'));
    } else {
      if (!$subprojects) {
        $subproject_status = $this->renderStatus(
          'fa-check grey',
          pht('None Created'),
          pht('You can create subprojects for this project.'));
      } else {
        $subproject_status = $this->renderStatus(
          'fa-check green',
          pht('Has Subprojects'),
          pht(
            'This project has subprojects.'));
      }
    }

    $view->addProperty(pht('Subprojects'), $subproject_status);

    return $view;
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

    $curtain = $this->newCurtainView($project);

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

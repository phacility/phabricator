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

    $has_support = $project->supportsSubprojects();

    if ($has_support) {
      $subprojects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withParentProjectPHIDs(array($project->getPHID()))
        ->needImages(true)
        ->withIsMilestone(false)
        ->execute();
    } else {
      $subprojects = array();
    }

    $can_create = $can_edit && $has_support;

    if ($project->getHasSubprojects()) {
      $button_text = pht('Create Subproject');
    } else {
      $button_text = pht('Add Subprojects');
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Subprojects'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref("/project/edit/?parent={$id}")
          ->setIconFont('fa-plus')
          ->setDisabled(!$can_create)
          ->setWorkflow(!$can_create)
          ->setText($button_text));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    if (!$has_support) {
      $no_support = pht(
        'This project is a milestone. Milestones can not have subprojects.');

      $info_view = id(new PHUIInfoView())
        ->setErrors(array($no_support))
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING);

      $box->setInfoView($info_view);
    }

    $box->setObjectList(
      id(new PhabricatorProjectListView())
        ->setUser($viewer)
        ->setProjects($subprojects)
        ->renderList());

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("subprojects/{$id}/");

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Subprojects'));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), pht('Subprojects')))
      ->appendChild($box);
  }

}

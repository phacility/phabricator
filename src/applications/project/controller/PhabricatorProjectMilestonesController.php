<?php

final class PhabricatorProjectMilestonesController
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

    $has_support = $project->supportsMilestones();
    if ($has_support) {
      $milestones = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withParentProjectPHIDs(array($project->getPHID()))
        ->needImages(true)
        ->withIsMilestone(true)
        ->setOrder('newest')
        ->execute();
    } else {
      $milestones = array();
    }

    $can_create = $can_edit && $has_support;

    if ($project->getHasMilestones()) {
      $button_text = pht('Create Next Milestone');
    } else {
      $button_text = pht('Add Milestones');
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Milestones'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref("/project/edit/?milestone={$id}")
          ->setIconFont('fa-plus')
          ->setDisabled(!$can_create)
          ->setWorkflow(!$can_create)
          ->setText($button_text));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    if (!$has_support) {
      $no_support = pht(
        'This project is a milestone. Milestones can not have their own '.
        'milestones.');

      $info_view = id(new PHUIInfoView())
        ->setErrors(array($no_support))
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING);

      $box->setInfoView($info_view);
    }

    $box->setObjectList(
      id(new PhabricatorProjectListView())
        ->setUser($viewer)
        ->setProjects($milestones)
        ->renderList());

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("milestones/{$id}/");

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Milestones'));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), pht('Milestones')))
      ->appendChild($box);
  }

}

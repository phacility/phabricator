<?php

final class PhabricatorProjectColumnDetailController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $project_id = $request->getURIData('projectID');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->withIDs(array($project_id))
      ->needImages(true)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    $this->setProject($project);

    $project_id = $project->getID();

    $column = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
        ->executeOne();
    if (!$column) {
      return new Aphront404Response();
    }

    $timeline = $this->buildTransactionTimeline(
      $column,
      new PhabricatorProjectColumnTransactionQuery());
    $timeline->setShouldTerminate(true);

    $title = $column->getDisplayName();

    $header = $this->buildHeaderView($column);
    $actions = $this->buildActionView($column);
    $properties = $this->buildPropertyView($column, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Workboard'), "/project/board/{$project_id}/");
    $crumbs->addTextCrumb(pht('Column: %s', $title));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $nav = $this->getProfileMenu();

    return $this->newPage()
      ->setTitle($title)
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $box,
          $timeline,
        ));
  }

  private function buildHeaderView(PhabricatorProjectColumn $column) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($column->getDisplayName());

    if ($column->isHidden()) {
      $header->setStatus('fa-ban', 'dark', pht('Hidden'));
    }

    return $header;
  }

  private function buildActionView(PhabricatorProjectColumn $column) {
    $viewer = $this->getRequest()->getUser();

    $id = $column->getID();
    $project_id = $this->getProject()->getID();
    $base_uri = '/board/'.$project_id.'/';

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Column'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI($base_uri.'edit/'.$id.'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }

  private function buildPropertyView(
    PhabricatorProjectColumn $column,
    PhabricatorActionListView $actions) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($column)
      ->setActionList($actions);

    $limit = $column->getPointLimit();
    if ($limit === null) {
      $limit_text = pht('No Limit');
    } else {
      $limit_text = $limit;
    }
    $properties->addProperty(pht('Point Limit'), $limit_text);

    return $properties;
  }

}

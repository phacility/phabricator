<?php

final class PhabricatorProjectColumnDetailController
  extends PhabricatorProjectBoardController {

  private $id;
  private $projectID;

  public function willProcessRequest(array $data) {
    $this->projectID = $data['projectID'];
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->withIDs(array($this->projectID))
      ->executeOne();

    if (!$project) {
      return new Aphront404Response();
    }
    $this->setProject($project);

    $column = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
        ->executeOne();
    if (!$column) {
      return new Aphront404Response();
    }

    $xactions = id(new PhabricatorProjectColumnTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($column->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($column->getPHID())
      ->setTransactions($xactions);

    $title = pht('%s', $column->getName());
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Board'),
      $this->getApplicationURI('board/'.$project->getID().'/'));
    $crumbs->addTextCrumb($title);

    $header = $this->buildHeaderView($column);
    $actions = $this->buildActionView($column);
    $properties = $this->buildPropertyView($column, $actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildHeaderView(PhabricatorProjectColumn $column) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($column->getName())
      ->setPolicyObject($column);

    if ($column->isDeleted()) {
      $header->setStatus('reject', 'red', pht('Deleted'));
    }

    return $header;
  }

  private function buildActionView(PhabricatorProjectColumn $column) {
    $viewer = $this->getRequest()->getUser();

    $id = $column->getID();
    $project_id = $this->getProject()->getID();
    $base_uri = '/board/'.$project_id.'/';

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI($this->getApplicationURI($base_uri.'column/'.$id.'/'))
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit column'))
        ->setIcon('edit')
        ->setHref($this->getApplicationURI($base_uri.'edit/'.$id.'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if (!$column->isDeleted()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Delete column'))
          ->setIcon('delete')
          ->setHref($this->getApplicationURI($base_uri.'delete/'.$id.'/'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate column'))
          ->setIcon('enable')
          ->setHref($this->getApplicationURI($base_uri.'delete/'.$id.'/'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

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

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $column);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    return $properties;
  }

}

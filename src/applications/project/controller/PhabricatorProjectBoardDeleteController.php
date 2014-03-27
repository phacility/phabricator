<?php

final class PhabricatorProjectBoardDeleteController
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
          PhabricatorPolicyCapability::CAN_EDIT,
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
          PhabricatorPolicyCapability::CAN_EDIT))
      ->executeOne();
    if (!$column) {
      return new Aphront404Response();
    }

    $error_view = null;
    $column_phid = $column->getPHID();
    $has_task_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $column_phid,
      PhabricatorEdgeConfig::TYPE_COLUMN_HAS_OBJECT);

    if ($has_task_phids) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Column has Tasks!'));
      if ($column->isDeleted()) {
        $error_view->setErrors(array(pht(
          'A column can not be activated if it has tasks '.
          'in it. Please remove the tasks and try again.')));
      } else {
        $error_view->setErrors(array(pht(
          'A column can not be deleted if it has tasks '.
          'in it. Please remove the tasks and try again.')));
      }
    }

    $view_uri = $this->getApplicationURI(
      '/board/'.$this->projectID.'/column/'.$this->id.'/');

    if ($request->isFormPost() && !$error_view) {
      if ($column->isDeleted()) {
        $new_status = PhabricatorProjectColumn::STATUS_ACTIVE;
      } else {
        $new_status = PhabricatorProjectColumn::STATUS_DELETED;
      }

      $type_status = PhabricatorProjectColumnTransaction::TYPE_STATUS;
      $xactions = array(id(new PhabricatorProjectColumnTransaction())
        ->setTransactionType($type_status)
        ->setNewValue($new_status));

      $editor = id(new PhabricatorProjectColumnTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($column, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($column->isDeleted()) {
      $title = pht('Activate Column');
    } else {
      $title = pht('Delete Column');
    }
    $submit = $title;
    if ($error_view) {
      $body = $error_view;
    } else if ($column->isDeleted()) {
      $body = pht('Are you sure you want to activate this column?');
    } else {
      $body = pht('Are you sure you want to delete this column?');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle($title)
      ->appendChild($body)
      ->setDisableWorkflowOnCancel(true)
      ->addSubmitButton($title)
      ->addCancelButton($view_uri);

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);

  }
}

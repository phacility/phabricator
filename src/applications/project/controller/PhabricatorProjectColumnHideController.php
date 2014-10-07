<?php

final class PhabricatorProjectColumnHideController
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
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$column) {
      return new Aphront404Response();
    }

    $column_phid = $column->getPHID();

    $view_uri = $this->getApplicationURI('/board/'.$this->projectID.'/');
    $view_uri = new PhutilURI($view_uri);
    foreach ($request->getPassthroughRequestData() as $key => $value) {
      $view_uri->setQueryParam($key, $value);
    }

    if ($column->isDefaultColumn()) {
      return $this->newDialog()
        ->setTitle(pht('Can Not Hide Default Column'))
        ->appendParagraph(
          pht('You can not hide the default/backlog column on a board.'))
        ->addCancelButton($view_uri, pht('Okay'));
    }

    if ($request->isFormPost()) {
      if ($column->isHidden()) {
        $new_status = PhabricatorProjectColumn::STATUS_ACTIVE;
      } else {
        $new_status = PhabricatorProjectColumn::STATUS_HIDDEN;
      }

      $type_status = PhabricatorProjectColumnTransaction::TYPE_STATUS;
      $xactions = array(id(new PhabricatorProjectColumnTransaction())
        ->setTransactionType($type_status)
        ->setNewValue($new_status),
      );

      $editor = id(new PhabricatorProjectColumnTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($column, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($column->isHidden()) {
      $title = pht('Show Column');
    } else {
      $title = pht('Hide Column');
    }

    if ($column->isHidden()) {
      $body = pht(
        'Are you sure you want to show this column?');
    } else {
      $body = pht(
        'Are you sure you want to hide this column? It will no longer '.
        'appear on the workboard.');
    }

    $dialog = $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle($title)
      ->appendChild($body)
      ->setDisableWorkflowOnCancel(true)
      ->addCancelButton($view_uri)
      ->addSubmitButton($title);

    foreach ($request->getPassthroughRequestData() as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }
}

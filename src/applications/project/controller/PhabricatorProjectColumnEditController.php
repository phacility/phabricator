<?php

final class PhabricatorProjectColumnEditController
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
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($project_id))
      ->needImages(true)
      ->executeOne();

    if (!$project) {
      return new Aphront404Response();
    }
    $this->setProject($project);

    $is_new = ($id ? false : true);

    if (!$is_new) {
      $column = id(new PhabricatorProjectColumnQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$column) {
        return new Aphront404Response();
      }
    } else {
      $column = PhabricatorProjectColumn::initializeNewColumn($viewer);
    }

    $e_name = null;
    $e_limit = null;

    $v_limit = $column->getPointLimit();
    $v_name = $column->getName();

    $validation_exception = null;
    $base_uri = '/board/'.$project_id.'/';
    $view_uri = $this->getApplicationURI($base_uri);

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_limit = $request->getStr('limit');

      if ($is_new) {
        $column->setProjectPHID($project->getPHID());
        $column->attachProject($project);

        $columns = id(new PhabricatorProjectColumnQuery())
          ->setViewer($viewer)
          ->withProjectPHIDs(array($project->getPHID()))
          ->execute();

        $new_sequence = 1;
        if ($columns) {
          $values = mpull($columns, 'getSequence');
          $new_sequence = max($values) + 1;
        }
        $column->setSequence($new_sequence);
      }

      $xactions = array();

      $type_name = PhabricatorProjectColumnTransaction::TYPE_NAME;
      $type_limit = PhabricatorProjectColumnTransaction::TYPE_LIMIT;

      if (!$column->getProxy()) {
        $xactions[] = id(new PhabricatorProjectColumnTransaction())
          ->setTransactionType($type_name)
          ->setNewValue($v_name);
      }

      $xactions[] = id(new PhabricatorProjectColumnTransaction())
        ->setTransactionType($type_limit)
        ->setNewValue($v_limit);

      try {
        $editor = id(new PhabricatorProjectColumnTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($column, $xactions);
        return id(new AphrontRedirectResponse())->setURI($view_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $e_name = $ex->getShortMessage($type_name);
        $e_limit = $ex->getShortMessage($type_limit);
        $validation_exception = $ex;
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($request->getUser());

    if (!$column->getProxy()) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setValue($v_name)
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setError($e_name));
    }

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setValue($v_limit)
        ->setLabel(pht('Point Limit'))
        ->setName('limit')
        ->setError($e_limit)
        ->setCaption(
          pht('Maximum number of points of tasks allowed in the column.')));

    if ($is_new) {
      $title = pht('Create Column');
      $submit = pht('Create Column');
    } else {
      $title = pht('Edit %s', $column->getDisplayName());
      $submit = pht('Save Column');
    }

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle($title)
      ->appendForm($form)
      ->setValidationException($validation_exception)
      ->addCancelButton($view_uri)
      ->addSubmitButton($submit);

  }
}

<?php

final class PhabricatorRoleColumnEditController
  extends PhabricatorRoleBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $role_id = $request->getURIData('roleID');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($role_id))
      ->needImages(true)
      ->executeOne();

    if (!$role) {
      return new Aphront404Response();
    }
    $this->setRole($role);

    $is_new = ($id ? false : true);

    if (!$is_new) {
      $column = id(new PhabricatorRoleColumnQuery())
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
      $column = PhabricatorRoleColumn::initializeNewColumn($viewer);
    }

    $e_name = null;
    $e_limit = null;

    $v_limit = $column->getPointLimit();
    $v_name = $column->getName();

    $validation_exception = null;
    $view_uri = $role->getWorkboardURI();

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_limit = $request->getStr('limit');

      if ($is_new) {
        $column->setRolePHID($role->getPHID());
        $column->attachRole($role);

        $columns = id(new PhabricatorRoleColumnQuery())
          ->setViewer($viewer)
          ->withRolePHIDs(array($role->getPHID()))
          ->execute();

        $new_sequence = 1;
        if ($columns) {
          $values = mpull($columns, 'getSequence');
          $new_sequence = max($values) + 1;
        }
        $column->setSequence($new_sequence);
      }

      $xactions = array();

      $type_name = PhabricatorRoleColumnNameTransaction::TRANSACTIONTYPE;
      $type_limit = PhabricatorRoleColumnLimitTransaction::TRANSACTIONTYPE;

      if (!$column->getProxy()) {
        $xactions[] = id(new PhabricatorRoleColumnTransaction())
          ->setTransactionType($type_name)
          ->setNewValue($v_name);
      }

      $xactions[] = id(new PhabricatorRoleColumnTransaction())
        ->setTransactionType($type_limit)
        ->setNewValue($v_limit);

      try {
        $editor = id(new PhabricatorRoleColumnTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
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

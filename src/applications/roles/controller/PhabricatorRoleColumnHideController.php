<?php

final class PhabricatorRoleColumnHideController
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
      ->executeOne();

    if (!$role) {
      return new Aphront404Response();
    }
    $this->setRole($role);

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

    $column_phid = $column->getPHID();

    $view_uri = $role->getWorkboardURI();
    $view_uri = new PhutilURI($view_uri);
    foreach ($request->getPassthroughRequestData() as $key => $value) {
      $view_uri->replaceQueryParam($key, $value);
    }

    if ($column->isDefaultColumn()) {
      return $this->newDialog()
        ->setTitle(pht('Can Not Hide Default Column'))
        ->appendParagraph(
          pht('You can not hide the default/backlog column on a board.'))
        ->addCancelButton($view_uri, pht('Okay'));
    }

    $proxy = $column->getProxy();

    if ($request->isFormPost()) {
      if ($proxy) {
        if ($proxy->isArchived()) {
          $new_status = PhabricatorRoleStatus::STATUS_ACTIVE;
        } else {
          $new_status = PhabricatorRoleStatus::STATUS_ARCHIVED;
        }

        $xactions = array();

        $xactions[] = id(new PhabricatorRoleTransaction())
          ->setTransactionType(
              PhabricatorRoleStatusTransaction::TRANSACTIONTYPE)
          ->setNewValue($new_status);

        id(new PhabricatorRoleTransactionEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
          ->applyTransactions($proxy, $xactions);
      } else {
        if ($column->isHidden()) {
          $new_status = PhabricatorRoleColumn::STATUS_ACTIVE;
        } else {
          $new_status = PhabricatorRoleColumn::STATUS_HIDDEN;
        }

        $type_status =
          PhabricatorRoleColumnStatusTransaction::TRANSACTIONTYPE;

        $xactions = array(
          id(new PhabricatorRoleColumnTransaction())
            ->setTransactionType($type_status)
            ->setNewValue($new_status),
        );

        $editor = id(new PhabricatorRoleColumnTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($column, $xactions);
      }

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($proxy) {
      if ($column->isHidden()) {
        $title = pht('Activate and Show Column');
        $body = pht(
          'This column is hidden because it represents an archived '.
          'subrole. Do you want to activate the subrole so the '.
          'column is visible again?');
        $button = pht('Activate Subrole');
      } else {
        $title = pht('Archive and Hide Column');
        $body = pht(
          'This column is visible because it represents an active '.
          'subrole. Do you want to hide the column by archiving the '.
          'subrole?');
        $button = pht('Archive Subrole');
      }
    } else {
      if ($column->isHidden()) {
        $title = pht('Show Column');
        $body = pht('Are you sure you want to show this column?');
        $button = pht('Show Column');
      } else {
        $title = pht('Hide Column');
        $body = pht(
          'Are you sure you want to hide this column? It will no longer '.
          'appear on the workboard.');
        $button = pht('Hide Column');
      }
    }

    $dialog = $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle($title)
      ->appendChild($body)
      ->setDisableWorkflowOnCancel(true)
      ->addCancelButton($view_uri)
      ->addSubmitButton($button);

    foreach ($request->getPassthroughRequestData() as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }
}

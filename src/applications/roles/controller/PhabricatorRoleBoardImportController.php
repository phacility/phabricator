<?php

final class PhabricatorRoleBoardImportController
  extends PhabricatorRoleBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
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

    $role_id = $role->getID();
    $board_uri = $this->getApplicationURI("board/{$role_id}/");

    // See PHI1025. We only want to prevent the import if the board already has
    // real columns. If it has proxy columns (for example, for milestones) you
    // can still import columns from another board.
    $columns = id(new PhabricatorRoleColumnQuery())
      ->setViewer($viewer)
      ->withRolePHIDs(array($role->getPHID()))
      ->withIsProxyColumn(false)
      ->execute();
    if ($columns) {
      return $this->newDialog()
        ->setTitle(pht('Workboard Already Has Columns'))
        ->appendParagraph(
          pht(
            'You can not import columns into this workboard because it '.
            'already has columns. You can only import into an empty '.
            'workboard.'))
        ->addCancelButton($board_uri);
    }

    if ($request->isFormPost()) {
      $import_phid = $request->getArr('importRolePHID');
      $import_phid = reset($import_phid);

      $import_columns = id(new PhabricatorRoleColumnQuery())
        ->setViewer($viewer)
        ->withRolePHIDs(array($import_phid))
        ->withIsProxyColumn(false)
        ->execute();
      if (!$import_columns) {
        return $this->newDialog()
          ->setTitle(pht('Source Workboard Has No Columns'))
          ->appendParagraph(
            pht(
              'You can not import columns from that workboard because it has '.
              'no importable columns.'))
          ->addCancelButton($board_uri);
      }

      $table = id(new PhabricatorRoleColumn())
        ->openTransaction();
      foreach ($import_columns as $import_column) {
        if ($import_column->isHidden()) {
          continue;
        }

        $new_column = PhabricatorRoleColumn::initializeNewColumn($viewer)
          ->setSequence($import_column->getSequence())
          ->setRolePHID($role->getPHID())
          ->setName($import_column->getName())
          ->setProperties($import_column->getProperties())
          ->save();
      }
      $xactions = array();
      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(
            PhabricatorRoleWorkboardTransaction::TRANSACTIONTYPE)
        ->setNewValue(1);

      id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      $table->saveTransaction();

      return id(new AphrontRedirectResponse())->setURI($board_uri);
    }

    $role_selector = id(new AphrontFormTokenizerControl())
      ->setName('importRolePHID')
      ->setUser($viewer)
      ->setDatasource(id(new PhabricatorRoleDatasource())
        ->setParameters(array('mustHaveColumns' => true))
      ->setLimit(1));

    return $this->newDialog()
      ->setTitle(pht('Import Columns'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendParagraph(pht('Choose a role to import columns from:'))
      ->appendChild($role_selector)
      ->addCancelButton($board_uri)
      ->addSubmitButton(pht('Import'));
  }

}

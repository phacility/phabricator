<?php

final class PhabricatorProjectBoardImportController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $project_id = $request->getURIData('projectID');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($project_id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    $this->setProject($project);

    $project_id = $project->getID();
    $board_uri = $this->getApplicationURI("board/{$project_id}/");

    // See PHI1025. We only want to prevent the import if the board already has
    // real columns. If it has proxy columns (for example, for milestones) you
    // can still import columns from another board.
    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
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
      $import_phid = $request->getArr('importProjectPHID');
      $import_phid = reset($import_phid);

      $import_columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($viewer)
        ->withProjectPHIDs(array($import_phid))
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

      $table = id(new PhabricatorProjectColumn())
        ->openTransaction();
      foreach ($import_columns as $import_column) {
        if ($import_column->isHidden()) {
          continue;
        }

        $new_column = PhabricatorProjectColumn::initializeNewColumn($viewer)
          ->setSequence($import_column->getSequence())
          ->setProjectPHID($project->getPHID())
          ->setName($import_column->getName())
          ->setProperties($import_column->getProperties())
          ->save();
      }
      $xactions = array();
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(
            PhabricatorProjectWorkboardTransaction::TRANSACTIONTYPE)
        ->setNewValue(1);

      id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      $table->saveTransaction();

      return id(new AphrontRedirectResponse())->setURI($board_uri);
    }

    $proj_selector = id(new AphrontFormTokenizerControl())
      ->setName('importProjectPHID')
      ->setUser($viewer)
      ->setDatasource(id(new PhabricatorProjectDatasource())
        ->setParameters(array('mustHaveColumns' => true))
      ->setLimit(1));

    return $this->newDialog()
      ->setTitle(pht('Import Columns'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendParagraph(pht('Choose a project to import columns from:'))
      ->appendChild($proj_selector)
      ->addCancelButton($board_uri)
      ->addSubmitButton(pht('Import'));
  }

}

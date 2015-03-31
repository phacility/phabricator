<?php

final class PhabricatorProjectBoardImportController
  extends PhabricatorProjectBoardController {

  private $projectID;

  public function willProcessRequest(array $data) {
    $this->projectID = $data['projectID'];
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

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();
    if ($columns) {
      return new Aphront400Response();
    }

    $project_id = $project->getID();
    $board_uri = $this->getApplicationURI("board/{$project_id}/");

    if ($request->isFormPost()) {
      $import_phid = $request->getArr('importProjectPHID');
      $import_phid = reset($import_phid);

      $import_columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($viewer)
        ->withProjectPHIDs(array($import_phid))
        ->execute();
      if (!$import_columns) {
        return new Aphront400Response();
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

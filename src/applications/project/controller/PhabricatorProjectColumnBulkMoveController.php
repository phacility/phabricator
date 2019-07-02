<?php

final class PhabricatorProjectColumnBulkMoveController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $state = $this->getViewState();
    $board_uri = $state->newWorkboardURI();

    $layout_engine = $state->getLayoutEngine();

    $board_phid = $project->getPHID();
    $columns = $layout_engine->getColumns($board_phid);
    $columns = mpull($columns, null, 'getID');

    $column_id = $request->getURIData('columnID');
    $move_column = idx($columns, $column_id);
    if (!$move_column) {
      return new Aphront404Response();
    }

    $move_task_phids = $layout_engine->getColumnObjectPHIDs(
      $board_phid,
      $move_column->getPHID());

    $tasks = $state->getObjects();

    $move_tasks = array_select_keys($tasks, $move_task_phids);

    $move_tasks = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
      ->apply($move_tasks);

    if (!$move_tasks) {
      return $this->newDialog()
        ->setTitle(pht('No Movable Tasks'))
        ->appendParagraph(
          pht(
            'The selected column contains no visible tasks which you '.
            'have permission to move.'))
        ->addCancelButton($board_uri);
    }

    $move_project_phid = $project->getPHID();
    $move_column_phid = null;
    $move_project = null;
    $move_column = null;
    $columns = null;
    $errors = array();

    if ($request->isFormOrHiSecPost()) {
      $move_project_phid = head($request->getArr('moveProjectPHID'));
      if (!$move_project_phid) {
        $move_project_phid = $request->getStr('moveProjectPHID');
      }

      if (!$move_project_phid) {
        if ($request->getBool('hasProject')) {
          $errors[] = pht('Choose a project to move tasks to.');
        }
      } else {
        $target_project = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($move_project_phid))
          ->executeOne();
        if (!$target_project) {
          $errors[] = pht('You must choose a valid project.');
        } else if (!$project->getHasWorkboard()) {
          $errors[] = pht(
            'You must choose a project with a workboard.');
        } else {
          $move_project = $target_project;
        }
      }

      if ($move_project) {
        $move_engine = id(new PhabricatorBoardLayoutEngine())
          ->setViewer($viewer)
          ->setBoardPHIDs(array($move_project->getPHID()))
          ->setFetchAllBoards(true)
          ->executeLayout();

        $columns = $move_engine->getColumns($move_project->getPHID());
        $columns = mpull($columns, null, 'getPHID');

        foreach ($columns as $key => $column) {
          if ($column->isHidden()) {
            unset($columns[$key]);
          }
        }

        $move_column_phid = $request->getStr('moveColumnPHID');
        if (!$move_column_phid) {
          if ($request->getBool('hasColumn')) {
            $errors[] = pht('Choose a column to move tasks to.');
          }
        } else {
          if (empty($columns[$move_column_phid])) {
            $errors[] = pht(
              'Choose a valid column on the target workboard to move '.
              'tasks to.');
          } else if ($columns[$move_column_phid]->getID() == $column_id) {
            $errors[] = pht(
              'You can not move tasks from a column to itself.');
          } else {
            $move_column = $columns[$move_column_phid];
          }
        }
      }
    }

    if ($move_column && $move_project) {
      foreach ($move_tasks as $move_task) {
        $xactions = array();

        // If we're switching projects, get out of the old project first
        // and move to the new project.
        if ($move_project->getID() != $project->getID()) {
          $xactions[] = id(new ManiphestTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
            ->setMetadataValue(
              'edge:type',
              PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
            ->setNewValue(
              array(
                '-' => array(
                  $project->getPHID() => $project->getPHID(),
                ),
                '+' => array(
                  $move_project->getPHID() => $move_project->getPHID(),
                ),
              ));
        }

        $xactions[] = id(new ManiphestTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_COLUMNS)
          ->setNewValue(
            array(
              array(
                'columnPHID' => $move_column->getPHID(),
              ),
            ));

        $editor = id(new ManiphestTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->setCancelURI($board_uri);

        $editor->applyTransactions($move_task, $xactions);
      }

      return id(new AphrontRedirectResponse())
        ->setURI($board_uri);
    }

    if ($move_project) {
      $column_form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->appendControl(
          id(new AphrontFormSelectControl())
            ->setName('moveColumnPHID')
            ->setLabel(pht('Move to Column'))
            ->setValue($move_column_phid)
            ->setOptions(mpull($columns, 'getDisplayName', 'getPHID')));

      return $this->newWorkboardDialog()
        ->setTitle(pht('Move Tasks'))
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setErrors($errors)
        ->addHiddenInput('moveProjectPHID', $move_project->getPHID())
        ->addHiddenInput('hasColumn', true)
        ->addHiddenInput('hasProject', true)
        ->appendParagraph(
          pht(
            'Choose a column on the %s workboard to move tasks to:',
            $viewer->renderHandle($move_project->getPHID())))
        ->appendForm($column_form)
        ->addSubmitButton(pht('Move Tasks'))
        ->addCancelButton($board_uri);
    }

    if ($move_project_phid) {
      $move_project_phid_value = array($move_project_phid);
    } else {
      $move_project_phid_value = array();
    }

    $project_form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('moveProjectPHID')
          ->setLimit(1)
          ->setLabel(pht('Move to Project'))
          ->setValue($move_project_phid_value)
          ->setDatasource(new PhabricatorProjectDatasource()));

    return $this->newWorkboardDialog()
      ->setTitle(pht('Move Tasks'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setErrors($errors)
      ->addHiddenInput('hasProject', true)
      ->appendForm($project_form)
      ->addSubmitButton(pht('Continue'))
      ->addCancelButton($board_uri);
  }

}

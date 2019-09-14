<?php

final class PhabricatorProjectColumnBulkMoveController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    // See T13316. If we're operating in "column" mode, we're going to skip
    // the prompt for a project and just have the user select a target column.
    // In "project" mode, we prompt them for a project first.
    $is_column_mode = ($request->getURIData('mode') === 'column');

    $src_project = $this->getProject();
    $state = $this->getViewState();
    $board_uri = $state->newWorkboardURI();

    $layout_engine = $state->getLayoutEngine();

    $board_phid = $src_project->getPHID();
    $columns = $layout_engine->getColumns($board_phid);
    $columns = mpull($columns, null, 'getID');

    $column_id = $request->getURIData('columnID');
    $src_column = idx($columns, $column_id);
    if (!$src_column) {
      return new Aphront404Response();
    }

    $move_task_phids = $layout_engine->getColumnObjectPHIDs(
      $board_phid,
      $src_column->getPHID());

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

    $dst_project_phid = null;
    $dst_project = null;
    $has_project = false;
    if ($is_column_mode) {
      $has_project = true;
      $dst_project_phid = $src_project->getPHID();
    } else {
      if ($request->isFormOrHiSecPost()) {
        $has_project = $request->getStr('hasProject');
        if ($has_project) {
          // We may read this from a tokenizer input as an array, or from a
          // hidden input as a string.
          $dst_project_phid = head($request->getArr('dstProjectPHID'));
          if (!$dst_project_phid) {
            $dst_project_phid = $request->getStr('dstProjectPHID');
          }
        }
      }
    }

    $errors = array();
    $hidden = array();

    if ($has_project) {
      if (!$dst_project_phid) {
        $errors[] = pht('Choose a project to move tasks to.');
      } else {
        $dst_project = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($dst_project_phid))
          ->executeOne();
        if (!$dst_project) {
          $errors[] = pht('Choose a valid project to move tasks to.');
        }

        if (!$dst_project->getHasWorkboard()) {
          $errors[] = pht('You must choose a project with a workboard.');
          $dst_project = null;
        }
      }
    }

    if ($dst_project) {
      $same_project = ($src_project->getID() === $dst_project->getID());

      $layout_engine = id(new PhabricatorBoardLayoutEngine())
        ->setViewer($viewer)
        ->setBoardPHIDs(array($dst_project->getPHID()))
        ->setFetchAllBoards(true)
        ->executeLayout();

      $dst_columns = $layout_engine->getColumns($dst_project->getPHID());
      $dst_columns = mpull($dst_columns, null, 'getPHID');

      // Prevent moves to milestones or subprojects by selecting their
      // columns, since the implications aren't obvious and this doesn't
      // work the same way as normal column moves.
      foreach ($dst_columns as $key => $dst_column) {
        if ($dst_column->getProxyPHID()) {
          unset($dst_columns[$key]);
        }
      }

      $has_column = false;
      $dst_column = null;

      // If we're performing a move on the same board, default the
      // control value to the current column.
      if ($same_project) {
        $dst_column_phid = $src_column->getPHID();
      } else {
        $dst_column_phid = null;
      }

      if ($request->isFormOrHiSecPost()) {
        $has_column = $request->getStr('hasColumn');
        if ($has_column) {
          $dst_column_phid = $request->getStr('dstColumnPHID');
        }
      }

      if ($has_column) {
        $dst_column = idx($dst_columns, $dst_column_phid);
        if (!$dst_column) {
          $errors[] = pht('Choose a column to move tasks to.');
        } else {
          if ($dst_column->isHidden()) {
            $errors[] = pht('You can not move tasks to a hidden column.');
            $dst_column = null;
          } else if ($dst_column->getPHID() === $src_column->getPHID()) {
            $errors[] = pht('You can not move tasks from a column to itself.');
            $dst_column = null;
          }
        }
      }

      if ($dst_column) {
        foreach ($move_tasks as $move_task) {
          $xactions = array();

          // If we're switching projects, get out of the old project first
          // and move to the new project.
          if (!$same_project) {
            $xactions[] = id(new ManiphestTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
              ->setMetadataValue(
                'edge:type',
                PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
              ->setNewValue(
                array(
                  '-' => array(
                    $src_project->getPHID() => $src_project->getPHID(),
                  ),
                  '+' => array(
                    $dst_project->getPHID() => $dst_project->getPHID(),
                  ),
                ));
          }

          $xactions[] = id(new ManiphestTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_COLUMNS)
            ->setNewValue(
              array(
                array(
                  'columnPHID' => $dst_column->getPHID(),
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

        // If we did a move on the same workboard, redirect and preserve the
        // state parameters. If we moved to a different workboard, go there
        // with clean default state.
        if ($same_project) {
          $done_uri = $board_uri;
        } else {
          $done_uri = $dst_project->getWorkboardURI();
        }

        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }

      $title = pht('Move Tasks to Column');

      $form = id(new AphrontFormView())
        ->setViewer($viewer);

      // If we're moving between projects, add a reminder about which project
      // you selected in the previous step.
      if (!$is_column_mode) {
        $form->appendControl(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Project'))
            ->setValue($dst_project->getDisplayName()));
      }

      $column_options = array(
        'visible' => array(),
        'hidden' => array(),
      );

      $any_hidden = false;
      foreach ($dst_columns as $column) {
        if (!$column->isHidden()) {
          $group = 'visible';
        } else {
          $group = 'hidden';
        }

        $phid = $column->getPHID();
        $display_name = $column->getDisplayName();

        $column_options[$group][$phid] = $display_name;
      }

      if ($column_options['hidden']) {
        $column_options = array(
          pht('Visible Columns') => $column_options['visible'],
          pht('Hidden Columns') => $column_options['hidden'],
        );
      } else {
        $column_options = $column_options['visible'];
      }

      $form->appendControl(
        id(new AphrontFormSelectControl())
          ->setName('dstColumnPHID')
          ->setLabel(pht('Move to Column'))
          ->setValue($dst_column_phid)
          ->setOptions($column_options));

      $submit = pht('Move Tasks');

      $hidden['dstProjectPHID'] = $dst_project->getPHID();
      $hidden['hasColumn'] = true;
      $hidden['hasProject'] = true;
    } else {
      $title = pht('Move Tasks to Project');

      if ($dst_project_phid) {
        $dst_project_phid_value = array($dst_project_phid);
      } else {
        $dst_project_phid_value = array();
      }

      $form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setName('dstProjectPHID')
            ->setLimit(1)
            ->setLabel(pht('Move to Project'))
            ->setValue($dst_project_phid_value)
            ->setDatasource(new PhabricatorProjectDatasource()));

      $submit = pht('Continue');

      $hidden['hasProject'] = true;
    }

    $dialog = $this->newWorkboardDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle($title)
      ->setErrors($errors)
      ->appendForm($form)
      ->addSubmitButton($submit)
      ->addCancelButton($board_uri);

    foreach ($hidden as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }

}

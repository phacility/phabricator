<?php

final class PhabricatorRoleColumnBulkMoveController
  extends PhabricatorRoleBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadRole();
    if ($response) {
      return $response;
    }

    // See T13316. If we're operating in "column" mode, we're going to skip
    // the prompt for a role and just have the user select a target column.
    // In "role" mode, we prompt them for a role first.
    $is_column_mode = ($request->getURIData('mode') === 'column');

    $src_role = $this->getRole();
    $state = $this->getViewState();
    $board_uri = $state->newWorkboardURI();

    $layout_engine = $state->getLayoutEngine();

    $board_phid = $src_role->getPHID();
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

    $dst_role_phid = null;
    $dst_role = null;
    $has_role = false;
    if ($is_column_mode) {
      $has_role = true;
      $dst_role_phid = $src_role->getPHID();
    } else {
      if ($request->isFormOrHiSecPost()) {
        $has_role = $request->getStr('hasRole');
        if ($has_role) {
          // We may read this from a tokenizer input as an array, or from a
          // hidden input as a string.
          $dst_role_phid = head($request->getArr('dstRolePHID'));
          if (!$dst_role_phid) {
            $dst_role_phid = $request->getStr('dstRolePHID');
          }
        }
      }
    }

    $errors = array();
    $hidden = array();

    if ($has_role) {
      if (!$dst_role_phid) {
        $errors[] = pht('Choose a role to move tasks to.');
      } else {
        $dst_role = id(new PhabricatorRoleQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($dst_role_phid))
          ->executeOne();
        if (!$dst_role) {
          $errors[] = pht('Choose a valid role to move tasks to.');
        }

        if (!$dst_role->getHasWorkboard()) {
          $errors[] = pht('You must choose a role with a workboard.');
          $dst_role = null;
        }
      }
    }

    if ($dst_role) {
      $same_role = ($src_role->getID() === $dst_role->getID());

      $layout_engine = id(new PhabricatorRoleBoardLayoutEngine())
        ->setViewer($viewer)
        ->setBoardPHIDs(array($dst_role->getPHID()))
        ->setFetchAllBoards(true)
        ->executeLayout();

      $dst_columns = $layout_engine->getColumns($dst_role->getPHID());
      $dst_columns = mpull($dst_columns, null, 'getPHID');

      // Prevent moves to milestones or subroles by selecting their
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
      if ($same_role) {
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

          // If we're switching roles, get out of the old role first
          // and move to the new role.
          if (!$same_role) {
            $xactions[] = id(new ManiphestTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
              ->setMetadataValue(
                'edge:type',
                PhabricatorRoleObjectHasRoleEdgeType::EDGECONST)
              ->setNewValue(
                array(
                  '-' => array(
                    $src_role->getPHID() => $src_role->getPHID(),
                  ),
                  '+' => array(
                    $dst_role->getPHID() => $dst_role->getPHID(),
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
        if ($same_role) {
          $done_uri = $board_uri;
        } else {
          $done_uri = $dst_role->getWorkboardURI();
        }

        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }

      $title = pht('Move Tasks to Column');

      $form = id(new AphrontFormView())
        ->setViewer($viewer);

      // If we're moving between roles, add a reminder about which role
      // you selected in the previous step.
      if (!$is_column_mode) {
        $form->appendControl(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Role'))
            ->setValue($dst_role->getDisplayName()));
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

      $hidden['dstRolePHID'] = $dst_role->getPHID();
      $hidden['hasColumn'] = true;
      $hidden['hasRole'] = true;
    } else {
      $title = pht('Move Tasks to Role');

      if ($dst_role_phid) {
        $dst_role_phid_value = array($dst_role_phid);
      } else {
        $dst_role_phid_value = array();
      }

      $form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setName('dstRolePHID')
            ->setLimit(1)
            ->setLabel(pht('Move to Role'))
            ->setValue($dst_role_phid_value)
            ->setDatasource(new PhabricatorRoleDatasource()));

      $submit = pht('Continue');

      $hidden['hasRole'] = true;
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

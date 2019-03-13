<?php

final class ManiphestTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  private $moreValidationErrors = array();

  public function getEditorApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Maniphest Tasks');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_COLUMNS;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this task.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        return null;
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        return $xaction->getNewValue();
    }
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        return (bool)$new;
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        return;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        foreach ($xaction->getNewValue() as $move) {
          $this->applyBoardMove($object, $move);
        }
        break;
    }
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // When we change the status of a task, update tasks this tasks blocks
    // with a message to the effect of "alincoln resolved blocking task Txxx."
    $unblock_xaction = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
          $unblock_xaction = $xaction;
          break;
      }
    }

    if ($unblock_xaction !== null) {
      $blocked_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object->getPHID(),
        ManiphestTaskDependedOnByTaskEdgeType::EDGECONST);
      if ($blocked_phids) {
        // In theory we could apply these through policies, but that seems a
        // little bit surprising. For now, use the actor's vision.
        $blocked_tasks = id(new ManiphestTaskQuery())
          ->setViewer($this->getActor())
          ->withPHIDs($blocked_phids)
          ->needSubscriberPHIDs(true)
          ->needProjectPHIDs(true)
          ->execute();

        $old = $unblock_xaction->getOldValue();
        $new = $unblock_xaction->getNewValue();

        foreach ($blocked_tasks as $blocked_task) {
          $parent_xaction = id(new ManiphestTransaction())
            ->setTransactionType(
              ManiphestTaskUnblockTransaction::TRANSACTIONTYPE)
            ->setOldValue(array($object->getPHID() => $old))
            ->setNewValue(array($object->getPHID() => $new));

          if ($this->getIsNewObject()) {
            $parent_xaction->setMetadataValue('blocker.new', true);
          }

          $this->newSubEditor()
            ->setContinueOnNoEffect(true)
            ->setContinueOnMissingFields(true)
            ->applyTransactions($blocked_task, array($parent_xaction));
        }
      }
    }

    return $xactions;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Maniphest]');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    return 'maniphest-task-'.$object->getPHID();
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();

    if ($object->getOwnerPHID()) {
      $phids[] = $object->getOwnerPHID();
    }
    $phids[] = $this->getActingAsPHID();

    return $phids;
  }

  public function getMailTagsMap() {
    return array(
      ManiphestTransaction::MAILTAG_STATUS =>
        pht("A task's status changes."),
      ManiphestTransaction::MAILTAG_OWNER =>
        pht("A task's owner changes."),
      ManiphestTransaction::MAILTAG_PRIORITY =>
        pht("A task's priority changes."),
      ManiphestTransaction::MAILTAG_CC =>
        pht("A task's subscribers change."),
      ManiphestTransaction::MAILTAG_PROJECTS =>
        pht("A task's associated projects change."),
      ManiphestTransaction::MAILTAG_UNBLOCK =>
        pht("One of a task's subtasks changes status."),
      ManiphestTransaction::MAILTAG_COLUMN =>
        pht('A task is moved between columns on a workboard.'),
      ManiphestTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a task.'),
      ManiphestTransaction::MAILTAG_OTHER =>
        pht('Other task activity not listed above occurs.'),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new ManiphestReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("T{$id}: {$title}");
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    if ($this->getIsNewObject()) {
      $body->addRemarkupSection(
        pht('TASK DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('TASK DETAIL'),
      PhabricatorEnv::getProductionURI('/T'.$object->getID()));


    $board_phids = array();
    $type_columns = PhabricatorTransactions::TYPE_COLUMNS;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_columns) {
        $moves = $xaction->getNewValue();
        foreach ($moves as $move) {
          $board_phids[] = $move['boardPHID'];
        }
      }
    }

    if ($board_phids) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->requireActor())
        ->withPHIDs($board_phids)
        ->execute();

      foreach ($projects as $project) {
        $body->addLinkSection(
          pht('WORKBOARD'),
          PhabricatorEnv::getProductionURI(
            '/project/board/'.$project->getID().'/'));
      }
    }


    return $body;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return id(new HeraldManiphestTaskAdapter())
      ->setTask($object);
  }

  protected function adjustObjectForPolicyChecks(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $copy = parent::adjustObjectForPolicyChecks($object, $xactions);
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
          $copy->setOwnerPHID($xaction->getNewValue());
          break;
        default:
          break;
      }
    }

    return $copy;
  }

  protected function validateAllTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $errors = parent::validateAllTransactions($object, $xactions);

    if ($this->moreValidationErrors) {
      $errors = array_merge($errors, $this->moreValidationErrors);
    }

    foreach ($this->getLockValidationErrors($object, $xactions) as $error) {
      $errors[] = $error;
    }

    return $errors;
  }

  protected function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $actor = $this->getActor();
    $actor_phid = $actor->getPHID();

    $results = parent::expandTransactions($object, $xactions);

    $is_unassigned = ($object->getOwnerPHID() === null);

    $any_assign = false;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() ==
        ManiphestTaskOwnerTransaction::TRANSACTIONTYPE) {
        $any_assign = true;
        break;
      }
    }

    $is_open = !$object->isClosed();

    $new_status = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
          $new_status = $xaction->getNewValue();
          break;
      }
    }

    if ($new_status === null) {
      $is_closing = false;
    } else {
      $is_closing = ManiphestTaskStatus::isClosedStatus($new_status);
    }

    // If the task is not assigned, not being assigned, currently open, and
    // being closed, try to assign the actor as the owner.
    if ($is_unassigned && !$any_assign && $is_open && $is_closing) {
      $is_claim = ManiphestTaskStatus::isClaimStatus($new_status);

      // Don't assign the actor if they aren't a real user.
      // Don't claim the task if the status is configured to not claim.
      if ($actor_phid && $is_claim) {
        $results[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTaskOwnerTransaction::TRANSACTIONTYPE)
          ->setNewValue($actor_phid);
      }
    }

    // Automatically subscribe the author when they create a task.
    if ($this->getIsNewObject()) {
      if ($actor_phid) {
        $results[] = id(new ManiphestTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(
            array(
              '+' => array($actor_phid => $actor_phid),
            ));
      }
    }

    return $results;
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $results = parent::expandTransaction($object, $xaction);

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorTransactions::TYPE_COLUMNS:
        try {
          $more_xactions = $this->buildMoveTransaction($object, $xaction);
          foreach ($more_xactions as $more_xaction) {
            $results[] = $more_xaction;
          }
        } catch (Exception $ex) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            $ex->getMessage(),
            $xaction);
          $this->moreValidationErrors[] = $error;
        }
        break;
      case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
        // If this is a no-op update, don't expand it.
        $old_value = $object->getOwnerPHID();
        $new_value = $xaction->getNewValue();
        if ($old_value === $new_value) {
          break;
        }

        // When a task is reassigned, move the old owner to the subscriber
        // list so they're still in the loop.
        if ($old_value) {
          $results[] = id(new ManiphestTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setIgnoreOnNoEffect(true)
            ->setNewValue(
              array(
                '+' => array($old_value => $old_value),
              ));
        }
        break;
    }

    return $results;
  }

  private function buildMoveTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $new = $xaction->getNewValue();
    if (!is_array($new)) {
      $this->validateColumnPHID($new);
      $new = array($new);
    }

    $nearby_phids = array();
    foreach ($new as $key => $value) {
      if (!is_array($value)) {
        $this->validateColumnPHID($value);
        $value = array(
          'columnPHID' => $value,
        );
      }

      PhutilTypeSpec::checkMap(
        $value,
        array(
          'columnPHID' => 'string',
          'beforePHID' => 'optional string',
          'afterPHID' => 'optional string',
        ));

      $new[$key] = $value;

      if (!empty($value['beforePHID'])) {
        $nearby_phids[] = $value['beforePHID'];
      }

      if (!empty($value['afterPHID'])) {
        $nearby_phids[] = $value['afterPHID'];
      }
    }

    if ($nearby_phids) {
      $nearby_objects = id(new PhabricatorObjectQuery())
        ->setViewer($this->getActor())
        ->withPHIDs($nearby_phids)
        ->execute();
      $nearby_objects = mpull($nearby_objects, null, 'getPHID');
    } else {
      $nearby_objects = array();
    }

    $column_phids = ipull($new, 'columnPHID');
    if ($column_phids) {
      $columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($this->getActor())
        ->withPHIDs($column_phids)
        ->execute();
      $columns = mpull($columns, null, 'getPHID');
    } else {
      $columns = array();
    }

    $board_phids = mpull($columns, 'getProjectPHID');
    $object_phid = $object->getPHID();

    $object_phids = $nearby_phids;

    // Note that we may not have an object PHID if we're creating a new
    // object.
    if ($object_phid) {
      $object_phids[] = $object_phid;
    }

    if ($object_phids) {
      $layout_engine = id(new PhabricatorBoardLayoutEngine())
        ->setViewer($this->getActor())
        ->setBoardPHIDs($board_phids)
        ->setObjectPHIDs($object_phids)
        ->setFetchAllBoards(true)
        ->executeLayout();
    }

    foreach ($new as $key => $spec) {
      $column_phid = $spec['columnPHID'];
      $column = idx($columns, $column_phid);
      if (!$column) {
        throw new Exception(
          pht(
            'Column move transaction specifies column PHID "%s", but there '.
            'is no corresponding column with this PHID.',
            $column_phid));
      }

      $board_phid = $column->getProjectPHID();

      $nearby = array();

      if (!empty($spec['beforePHID'])) {
        $nearby['beforePHID'] = $spec['beforePHID'];
      }

      if (!empty($spec['afterPHID'])) {
        $nearby['afterPHID'] = $spec['afterPHID'];
      }

      if (count($nearby) > 1) {
        throw new Exception(
          pht(
            'Column move transaction moves object to multiple positions. '.
            'Specify only "beforePHID" or "afterPHID", not both.'));
      }

      foreach ($nearby as $where => $nearby_phid) {
        if (empty($nearby_objects[$nearby_phid])) {
          throw new Exception(
            pht(
              'Column move transaction specifies object "%s" as "%s", but '.
              'there is no corresponding object with this PHID.',
              $object_phid,
              $where));
        }

        $nearby_columns = $layout_engine->getObjectColumns(
          $board_phid,
          $nearby_phid);
        $nearby_columns = mpull($nearby_columns, null, 'getPHID');

        if (empty($nearby_columns[$column_phid])) {
          throw new Exception(
            pht(
              'Column move transaction specifies object "%s" as "%s" in '.
              'column "%s", but this object is not in that column!',
              $nearby_phid,
              $where,
              $column_phid));
        }
      }

      if ($object_phid) {
        $old_columns = $layout_engine->getObjectColumns(
          $board_phid,
          $object_phid);
        $old_column_phids = mpull($old_columns, 'getPHID');
      } else {
        $old_column_phids = array();
      }

      $spec += array(
        'boardPHID' => $board_phid,
        'fromColumnPHIDs' => $old_column_phids,
      );

      // Check if the object is already in this column, and isn't being moved.
      // We can just drop this column change if it has no effect.
      $from_map = array_fuse($spec['fromColumnPHIDs']);
      $already_here = isset($from_map[$column_phid]);
      $is_reordering = (bool)$nearby;

      if ($already_here && !$is_reordering) {
        unset($new[$key]);
      } else {
        $new[$key] = $spec;
      }
    }

    $new = array_values($new);
    $xaction->setNewValue($new);


    $more = array();

    // If we're moving the object into a column and it does not already belong
    // in the column, add the appropriate board. For normal columns, this
    // is the board PHID. For proxy columns, it is the proxy PHID, unless the
    // object is already a member of some descendant of the proxy PHID.

    // The major case where this can happen is moves via the API, but it also
    // happens when a user drags a task from the "Backlog" to a milestone
    // column.

    if ($object_phid) {
      $current_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $object_phid,
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $current_phids = array_fuse($current_phids);
    } else {
      $current_phids = array();
    }

    $add_boards = array();
    foreach ($new as $move) {
      $column_phid = $move['columnPHID'];
      $board_phid = $move['boardPHID'];
      $column = $columns[$column_phid];
      $proxy_phid = $column->getProxyPHID();

      // If this is a normal column, add the board if the object isn't already
      // associated.
      if (!$proxy_phid) {
        if (!isset($current_phids[$board_phid])) {
          $add_boards[] = $board_phid;
        }
        continue;
      }

      // If this is a proxy column but the object is already associated with
      // the proxy board, we don't need to do anything.
      if (isset($current_phids[$proxy_phid])) {
        continue;
      }

      // If this a proxy column and the object is already associated with some
      // descendant of the proxy board, we also don't need to do anything.
      $descendants = id(new PhabricatorProjectQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withAncestorProjectPHIDs(array($proxy_phid))
        ->execute();

      $found_descendant = false;
      foreach ($descendants as $descendant) {
        if (isset($current_phids[$descendant->getPHID()])) {
          $found_descendant = true;
          break;
        }
      }

      if ($found_descendant) {
        continue;
      }

      // Otherwise, we're moving the object to a proxy column which it is not
      // a member of yet, so add an association to the column's proxy board.

      $add_boards[] = $proxy_phid;
    }

    if ($add_boards) {
      $more[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
        ->setIgnoreOnNoEffect(true)
        ->setNewValue(
          array(
            '+' => array_fuse($add_boards),
          ));
    }

    return $more;
  }

  private function applyBoardMove($object, array $move) {
    $board_phid = $move['boardPHID'];
    $column_phid = $move['columnPHID'];
    $before_phid = idx($move, 'beforePHID');
    $after_phid = idx($move, 'afterPHID');

    $object_phid = $object->getPHID();

    // We're doing layout with the omnipotent viewer to make sure we don't
    // remove positions in columns that exist, but which the actual actor
    // can't see.
    $omnipotent_viewer = PhabricatorUser::getOmnipotentUser();

    $select_phids = array($board_phid);

    $descendants = id(new PhabricatorProjectQuery())
      ->setViewer($omnipotent_viewer)
      ->withAncestorProjectPHIDs($select_phids)
      ->execute();
    foreach ($descendants as $descendant) {
      $select_phids[] = $descendant->getPHID();
    }

    $board_tasks = id(new ManiphestTaskQuery())
      ->setViewer($omnipotent_viewer)
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_ANCESTOR,
        array($select_phids))
      ->execute();

    $board_tasks = mpull($board_tasks, null, 'getPHID');
    $board_tasks[$object_phid] = $object;

    // Make sure tasks are sorted by ID, so we lay out new positions in
    // a consistent way.
    $board_tasks = msort($board_tasks, 'getID');

    $object_phids = array_keys($board_tasks);

    $engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($omnipotent_viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setObjectPHIDs($object_phids)
      ->executeLayout();

    // TODO: This logic needs to be revised when we legitimately support
    // multiple column positions.
    $columns = $engine->getObjectColumns($board_phid, $object_phid);
    foreach ($columns as $column) {
      $engine->queueRemovePosition(
        $board_phid,
        $column->getPHID(),
        $object_phid);
    }

    if ($before_phid) {
      $engine->queueAddPositionBefore(
        $board_phid,
        $column_phid,
        $object_phid,
        $before_phid);
    } else if ($after_phid) {
      $engine->queueAddPositionAfter(
        $board_phid,
        $column_phid,
        $object_phid,
        $after_phid);
    } else {
      $engine->queueAddPosition(
        $board_phid,
        $column_phid,
        $object_phid);
    }

    $engine->applyPositionUpdates();
  }


  private function validateColumnPHID($value) {
    if (phid_get_type($value) == PhabricatorProjectColumnPHIDType::TYPECONST) {
      return;
    }

    throw new Exception(
      pht(
        'When moving objects between columns on a board, columns must '.
        'be identified by PHIDs. This transaction uses "%s" to identify '.
        'a column, but that is not a valid column PHID.',
        $value));
  }


  private function getLockValidationErrors($object, array $xactions) {
    $errors = array();

    $old_owner = $object->getOwnerPHID();
    $old_status = $object->getStatus();

    $new_owner = $old_owner;
    $new_status = $old_status;

    $owner_xaction = null;
    $status_xaction = null;

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
          $new_owner = $xaction->getNewValue();
          $owner_xaction = $xaction;
          break;
        case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
          $new_status = $xaction->getNewValue();
          $status_xaction = $xaction;
          break;
      }
    }

    $actor_phid = $this->getActingAsPHID();

    $was_locked = ManiphestTaskStatus::areEditsLockedInStatus(
      $old_status);
    $now_locked = ManiphestTaskStatus::areEditsLockedInStatus(
      $new_status);

    if (!$now_locked) {
      // If we're not ending in an edit-locked status, everything is good.
    } else if ($new_owner !== null) {
      // If we ending the edit with some valid owner, this is allowed for
      // now. We might need to revisit this.
    } else {
      // The edits end with the task locked and unowned. No one will be able
      // to edit it, so we forbid this. We try to be specific about what the
      // user did wrong.

      $owner_changed = ($old_owner && !$new_owner);
      $status_changed = ($was_locked !== $now_locked);
      $message = null;

      if ($status_changed && $owner_changed) {
        $message = pht(
          'You can not lock this task and unassign it at the same time '.
          'because no one will be able to edit it anymore. Lock the task '.
          'or remove the owner, but not both.');
        $problem_xaction = $status_xaction;
      } else if ($status_changed) {
        $message = pht(
          'You can not lock this task because it does not have an owner. '.
          'No one would be able to edit the task. Assign the task to an '.
          'owner before locking it.');
        $problem_xaction = $status_xaction;
      } else if ($owner_changed) {
        $message = pht(
          'You can not remove the owner of this task because it is locked '.
          'and no one would be able to edit the task. Reassign the task or '.
          'unlock it before removing the owner.');
        $problem_xaction = $owner_xaction;
      } else {
        // If the task was already broken, we don't have a transaction to
        // complain about so just let it through. In theory, this is
        // impossible since policy rules should kick in before we get here.
      }

      if ($message) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $problem_xaction->getTransactionType(),
          pht('Lock Error'),
          $message,
          $problem_xaction);
      }
    }

    return $errors;
  }

}

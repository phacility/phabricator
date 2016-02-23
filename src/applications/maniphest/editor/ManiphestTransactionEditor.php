<?php

final class ManiphestTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

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
    $types[] = ManiphestTransaction::TYPE_PRIORITY;
    $types[] = ManiphestTransaction::TYPE_STATUS;
    $types[] = ManiphestTransaction::TYPE_TITLE;
    $types[] = ManiphestTransaction::TYPE_DESCRIPTION;
    $types[] = ManiphestTransaction::TYPE_OWNER;
    $types[] = ManiphestTransaction::TYPE_SUBPRIORITY;
    $types[] = ManiphestTransaction::TYPE_PROJECT_COLUMN;
    $types[] = ManiphestTransaction::TYPE_MERGED_INTO;
    $types[] = ManiphestTransaction::TYPE_MERGED_FROM;
    $types[] = ManiphestTransaction::TYPE_UNBLOCK;
    $types[] = ManiphestTransaction::TYPE_PARENT;
    $types[] = ManiphestTransaction::TYPE_COLUMN;
    $types[] = ManiphestTransaction::TYPE_COVER_IMAGE;
    $types[] = ManiphestTransaction::TYPE_POINTS;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
        if ($this->getIsNewObject()) {
          return null;
        }
        return (int)$object->getPriority();
      case ManiphestTransaction::TYPE_STATUS:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getStatus();
      case ManiphestTransaction::TYPE_TITLE:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getTitle();
      case ManiphestTransaction::TYPE_DESCRIPTION:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getDescription();
      case ManiphestTransaction::TYPE_OWNER:
        return nonempty($object->getOwnerPHID(), null);
      case ManiphestTransaction::TYPE_PROJECT_COLUMN:
        // These are pre-populated.
        return $xaction->getOldValue();
      case ManiphestTransaction::TYPE_SUBPRIORITY:
        return $object->getSubpriority();
      case ManiphestTransaction::TYPE_COVER_IMAGE:
        return $object->getCoverImageFilePHID();
      case ManiphestTransaction::TYPE_POINTS:
        $points = $object->getPoints();
        if ($points !== null) {
          $points = (double)$points;
        }
        return $points;
      case ManiphestTransaction::TYPE_MERGED_INTO:
      case ManiphestTransaction::TYPE_MERGED_FROM:
        return null;
      case ManiphestTransaction::TYPE_PARENT:
      case ManiphestTransaction::TYPE_COLUMN:
        return null;
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
        return (int)$xaction->getNewValue();
      case ManiphestTransaction::TYPE_OWNER:
        return nonempty($xaction->getNewValue(), null);
      case ManiphestTransaction::TYPE_STATUS:
      case ManiphestTransaction::TYPE_TITLE:
      case ManiphestTransaction::TYPE_DESCRIPTION:
      case ManiphestTransaction::TYPE_SUBPRIORITY:
      case ManiphestTransaction::TYPE_PROJECT_COLUMN:
      case ManiphestTransaction::TYPE_MERGED_INTO:
      case ManiphestTransaction::TYPE_MERGED_FROM:
      case ManiphestTransaction::TYPE_UNBLOCK:
      case ManiphestTransaction::TYPE_COVER_IMAGE:
        return $xaction->getNewValue();
      case ManiphestTransaction::TYPE_PARENT:
      case ManiphestTransaction::TYPE_COLUMN:
        return $xaction->getNewValue();
      case ManiphestTransaction::TYPE_POINTS:
        $value = $xaction->getNewValue();
        if (!strlen($value)) {
          $value = null;
        }
        if ($value !== null) {
          $value = (double)$value;
        }
        return $value;
    }
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PROJECT_COLUMN:
        $new_column_phids = $new['columnPHIDs'];
        $old_column_phids = $old['columnPHIDs'];
        sort($new_column_phids);
        sort($old_column_phids);
        return ($old !== $new);
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PRIORITY:
        return $object->setPriority($xaction->getNewValue());
      case ManiphestTransaction::TYPE_STATUS:
        return $object->setStatus($xaction->getNewValue());
      case ManiphestTransaction::TYPE_TITLE:
        return $object->setTitle($xaction->getNewValue());
      case ManiphestTransaction::TYPE_DESCRIPTION:
        return $object->setDescription($xaction->getNewValue());
      case ManiphestTransaction::TYPE_OWNER:
        $phid = $xaction->getNewValue();

        // Update the "ownerOrdering" column to contain the full name of the
        // owner, if the task is assigned.

        $handle = null;
        if ($phid) {
          $handle = id(new PhabricatorHandleQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($phid))
            ->executeOne();
        }

        if ($handle) {
          $object->setOwnerOrdering($handle->getName());
        } else {
          $object->setOwnerOrdering(null);
        }

        return $object->setOwnerPHID($phid);
      case ManiphestTransaction::TYPE_SUBPRIORITY:
        $object->setSubpriority($xaction->getNewValue());
        return;
      case ManiphestTransaction::TYPE_PROJECT_COLUMN:
        // these do external (edge) updates
        return;
      case ManiphestTransaction::TYPE_MERGED_INTO:
        $object->setStatus(ManiphestTaskStatus::getDuplicateStatus());
        return;
      case ManiphestTransaction::TYPE_COVER_IMAGE:
        $file_phid = $xaction->getNewValue();

        if ($file_phid) {
          $file = id(new PhabricatorFileQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($file_phid))
            ->executeOne();
        } else {
          $file = null;
        }

        if (!$file || !$file->isTransformableImage()) {
          $object->setProperty('cover.filePHID', null);
          $object->setProperty('cover.thumbnailPHID', null);
          return;
        }

        $xform_key = PhabricatorFileThumbnailTransform::TRANSFORM_WORKCARD;

        $xform = PhabricatorFileTransform::getTransformByKey($xform_key)
          ->executeTransform($file);

        $object->setProperty('cover.filePHID', $file->getPHID());
        $object->setProperty('cover.thumbnailPHID', $xform->getPHID());
        return;
      case ManiphestTransaction::TYPE_POINTS:
        $object->setPoints($xaction->getNewValue());
        return;
      case ManiphestTransaction::TYPE_MERGED_FROM:
      case ManiphestTransaction::TYPE_PARENT:
      case ManiphestTransaction::TYPE_COLUMN:
        return;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_PARENT:
        $parent_phid = $xaction->getNewValue();
        $parent_type = ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
        $task_phid = $object->getPHID();

        id(new PhabricatorEdgeEditor())
          ->addEdge($parent_phid, $parent_type, $task_phid)
          ->save();
        break;
      case ManiphestTransaction::TYPE_PROJECT_COLUMN:
        $board_phid = idx($xaction->getNewValue(), 'projectPHID');
        if (!$board_phid) {
          throw new Exception(
            pht(
              "Expected '%s' in column transaction.",
              'projectPHID'));
        }

        $old_phids = idx($xaction->getOldValue(), 'columnPHIDs', array());
        $new_phids = idx($xaction->getNewValue(), 'columnPHIDs', array());
        if (count($new_phids) !== 1) {
          throw new Exception(
            pht(
              "Expected exactly one '%s' in column transaction.",
              'columnPHIDs'));
        }

        $before_phid = idx($xaction->getNewValue(), 'beforePHID');
        $after_phid = idx($xaction->getNewValue(), 'afterPHID');

        if (!$before_phid && !$after_phid && ($old_phids == $new_phids)) {
          // If we are not moving the object between columns and also not
          // reordering the position, this is a move on some other order
          // (like priority). We can leave the positions untouched and just
          // bail, there's no work to be done.
          return;
        }

        // Otherwise, we're either moving between columns or adjusting the
        // object's position in the "natural" ordering, so we do need to update
        // some rows.

        $object_phid = $object->getPHID();

        // We're doing layout with the ominpotent viewer to make sure we don't
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

        // TODO: This logic needs to be revised if we legitimately support
        // multiple column positions.

        // NOTE: When a task is newly created, it's implicitly added to the
        // backlog but we don't currently record that in the "$old_phids". Just
        // clean it up for now.
        $columns = $engine->getObjectColumns($board_phid, $object_phid);
        foreach ($columns as $column) {
          $engine->queueRemovePosition(
            $board_phid,
            $column->getPHID(),
            $object_phid);
        }

        // Remove all existing column positions on the board.
        foreach ($old_phids as $column_phid) {
          $engine->queueRemovePosition(
            $board_phid,
            $column_phid,
            $object_phid);
        }

        // Add new positions.
        foreach ($new_phids as $column_phid) {
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
        }

        $engine->applyPositionUpdates();

        break;
      default:
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
        case ManiphestTransaction::TYPE_STATUS:
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
            ->setTransactionType(ManiphestTransaction::TYPE_UNBLOCK)
            ->setOldValue(array($object->getPHID() => $old))
            ->setNewValue(array($object->getPHID() => $new));

          if ($this->getIsNewObject()) {
            $parent_xaction->setMetadataValue('blocker.new', true);
          }

          id(new ManiphestTransactionEditor())
            ->setActor($this->getActor())
            ->setActingAsPHID($this->getActingAsPHID())
            ->setContentSource($this->getContentSource())
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
    return PhabricatorEnv::getEnvConfig('metamta.maniphest.subject-prefix');
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
        pht('One of the tasks a task is blocked by changes status.'),
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
      ->setSubject("T{$id}: {$title}")
      ->addHeader('Thread-Topic', "T{$id}: ".$object->getOriginalTitle());
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
    $type_column = ManiphestTransaction::TYPE_PROJECT_COLUMN;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_column) {
        $new = $xaction->getNewValue();
        $project_phid = idx($new, 'projectPHID');
        if ($project_phid) {
          $board_phids[] = $project_phid;
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
    return $this->shouldSendMail($object, $xactions);
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

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    parent::requireCapabilities($object, $xaction);

    $app_capability_map = array(
      ManiphestTransaction::TYPE_PRIORITY =>
        ManiphestEditPriorityCapability::CAPABILITY,
      ManiphestTransaction::TYPE_STATUS =>
        ManiphestEditStatusCapability::CAPABILITY,
      ManiphestTransaction::TYPE_OWNER =>
        ManiphestEditAssignCapability::CAPABILITY,
      PhabricatorTransactions::TYPE_EDIT_POLICY =>
        ManiphestEditPoliciesCapability::CAPABILITY,
      PhabricatorTransactions::TYPE_VIEW_POLICY =>
        ManiphestEditPoliciesCapability::CAPABILITY,
    );


    $transaction_type = $xaction->getTransactionType();

    $app_capability = null;
    if ($transaction_type == PhabricatorTransactions::TYPE_EDGE) {
      switch ($xaction->getMetadataValue('edge:type')) {
        case PhabricatorProjectObjectHasProjectEdgeType::EDGECONST:
          $app_capability = ManiphestEditProjectsCapability::CAPABILITY;
          break;
      }
    } else {
      $app_capability = idx($app_capability_map, $transaction_type);
    }

    if ($app_capability) {
      $app = id(new PhabricatorApplicationQuery())
        ->setViewer($this->getActor())
        ->withClasses(array('PhabricatorManiphestApplication'))
        ->executeOne();
      PhabricatorPolicyFilter::requireCapability(
        $this->getActor(),
        $app,
        $app_capability);
    }
  }

  protected function adjustObjectForPolicyChecks(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $copy = parent::adjustObjectForPolicyChecks($object, $xactions);
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case ManiphestTransaction::TYPE_OWNER:
          $copy->setOwnerPHID($xaction->getNewValue());
          break;
        default:
          continue;
      }
    }

    return $copy;
  }

  /**
   * Get priorities for moving a task to a new priority.
   */
  public static function getEdgeSubpriority(
    $priority,
    $is_end) {

    $query = id(new ManiphestTaskQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPriorities(array($priority))
      ->setLimit(1);

    if ($is_end) {
      $query->setOrderVector(array('-priority', '-subpriority', '-id'));
    } else {
      $query->setOrderVector(array('priority', 'subpriority', 'id'));
    }

    $result = $query->executeOne();
    $step = (double)(2 << 32);

    if ($result) {
      $base = $result->getSubpriority();
      if ($is_end) {
        $sub = ($base - $step);
      } else {
        $sub = ($base + $step);
      }
    } else {
      $sub = 0;
    }

    return array($priority, $sub);
  }


  /**
   * Get priorities for moving a task before or after another task.
   */
  public static function getAdjacentSubpriority(
    ManiphestTask $dst,
    $is_after,
    $allow_recursion = true) {

    $query = id(new ManiphestTaskQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->setOrder(ManiphestTaskQuery::ORDER_PRIORITY)
      ->withPriorities(array($dst->getPriority()))
      ->setLimit(1);

    if ($is_after) {
      $query->setAfterID($dst->getID());
    } else {
      $query->setBeforeID($dst->getID());
    }

    $adjacent = $query->executeOne();

    $base = $dst->getSubpriority();
    $step = (double)(2 << 32);

    // If we find an adjacent task, we average the two subpriorities and
    // return the result.
    if ($adjacent) {
      $epsilon = 0.01;

      // If the adjacent task has a subpriority that is identical or very
      // close to the task we're looking at, we're going to move it and all
      // tasks with the same subpriority a little farther down the subpriority
      // scale.
      if ($allow_recursion &&
          (abs($adjacent->getSubpriority() - $base) < $epsilon)) {
        $conn_w = $adjacent->establishConnection('w');

        $min = ($adjacent->getSubpriority() - ($epsilon));
        $max = ($adjacent->getSubpriority() + ($epsilon));

        // Get all of the tasks with the similar subpriorities to the adjacent
        // task, including the adjacent task itself.
        $query = id(new ManiphestTaskQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPriorities(array($adjacent->getPriority()))
          ->withSubpriorityBetween($min, $max);

        if (!$is_after) {
          $query->setOrderVector(array('-priority', '-subpriority', '-id'));
        } else {
          $query->setOrderVector(array('priority', 'subpriority', 'id'));
        }

        $shift_all = $query->execute();
        $shift_last = last($shift_all);

        // Select the most extreme subpriority in the result set as the
        // base value.
        $shift_base = head($shift_all)->getSubpriority();

        // Find the subpriority before or after the task at the end of the
        // block.
        list($shift_pri, $shift_sub) = self::getAdjacentSubpriority(
          $shift_last,
          $is_after,
          $allow_recursion = false);

        $delta = ($shift_sub - $shift_base);
        $count = count($shift_all);

        $shift = array();
        $cursor = 1;
        foreach ($shift_all as $shift_task) {
          $shift_target = $shift_base + (($cursor / $count) * $delta);
          $cursor++;

          queryfx(
            $conn_w,
            'UPDATE %T SET subpriority = %f WHERE id = %d',
            $adjacent->getTableName(),
            $shift_target,
            $shift_task->getID());

          // If we're shifting the adjacent task, update it.
          if ($shift_task->getID() == $adjacent->getID()) {
            $adjacent->setSubpriority($shift_target);
          }

          // If we're shifting the original target task, update the base
          // subpriority.
          if ($shift_task->getID() == $dst->getID()) {
            $base = $shift_target;
          }
        }
      }

      $sub = ($adjacent->getSubpriority() + $base) / 2;
    } else {
      // Otherwise, we take a step away from the target's subpriority and
      // use that.
      if ($is_after) {
        $sub = ($base - $step);
      } else {
        $sub = ($base + $step);
      }
    }

    return array($dst->getPriority(), $sub);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case ManiphestTransaction::TYPE_TITLE:
        $missing = $this->validateIsEmptyTextField(
          $object->getTitle(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Task title is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case ManiphestTransaction::TYPE_PARENT:
        $with_effect = array();
        foreach ($xactions as $xaction) {
          $task_phid = $xaction->getNewValue();
          if (!$task_phid) {
            continue;
          }

          $with_effect[] = $xaction;

          $task = id(new ManiphestTaskQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($task_phid))
            ->executeOne();
          if (!$task) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Parent task identifier "%s" does not identify a visible '.
                'task.',
                $task_phid),
              $xaction);
          }
        }

        if ($with_effect && !$this->getIsNewObject()) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'You can only select a parent task when creating a '.
              'transaction for the first time.'),
            last($with_effect));
        }
        break;
      case ManiphestTransaction::TYPE_COLUMN:
        $with_effect = array();
        foreach ($xactions as $xaction) {
          $column_phid = $xaction->getNewValue();
          if (!$column_phid) {
            continue;
          }

          $with_effect[] = $xaction;

          $column = $this->loadProjectColumn($column_phid);
          if (!$column) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Column PHID "%s" does not identify a visible column.',
                $column_phid),
              $xaction);
          }
        }

        if ($with_effect && !$this->getIsNewObject()) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'You can only put a task into an initial column during task '.
              'creation.'),
            last($with_effect));
        }
        break;
      case ManiphestTransaction::TYPE_OWNER:
        foreach ($xactions as $xaction) {
          $old = $xaction->getOldValue();
          $new = $xaction->getNewValue();
          if (!strlen($new)) {
            continue;
          }

          if ($new === $old) {
            continue;
          }

          $assignee_list = id(new PhabricatorPeopleQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($new))
            ->execute();
          if (!$assignee_list) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'User "%s" is not a valid user.',
                $new),
              $xaction);
          }
        }
        break;
      case ManiphestTransaction::TYPE_COVER_IMAGE:
        foreach ($xactions as $xaction) {
          $old = $xaction->getOldValue();
          $new = $xaction->getNewValue();
          if (!$new) {
            continue;
          }

          if ($new === $old) {
            continue;
          }

          $file = id(new PhabricatorFileQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($new))
            ->executeOne();
          if (!$file) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('File "%s" is not valid.', $new),
              $xaction);
            continue;
          }

          if (!$file->isTransformableImage()) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('File "%s" is not a valid image file.', $new),
              $xaction);
            continue;
          }
        }
        break;

      case ManiphestTransaction::TYPE_POINTS:
        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();
          if (strlen($new) && !is_numeric($new)) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('Points value must be numeric or empty.'),
              $xaction);
            continue;
          }

          if ((double)$new < 0) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('Points value must be nonnegative.'),
              $xaction);
            continue;
          }
        }
        break;

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
      if ($xaction->getTransactionType() == ManiphestTransaction::TYPE_OWNER) {
        $any_assign = true;
        break;
      }
    }

    $is_open = !$object->isClosed();

    $new_status = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case ManiphestTransaction::TYPE_STATUS:
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
          ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
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

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_COLUMN:
        $column_phid = $xaction->getNewValue();
        if (!$column_phid) {
          break;
        }

        // When a task is created into a column, we also generate a transaction
        // to actually put it in that column.
        $column = $this->loadProjectColumn($column_phid);
        $results[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTransaction::TYPE_PROJECT_COLUMN)
          ->setOldValue(
            array(
              'projectPHID' => $column->getProjectPHID(),
              'columnPHIDs' => array(),
            ))
          ->setNewValue(
            array(
              'projectPHID' => $column->getProjectPHID(),
              'columnPHIDs' => array($column->getPHID()),
            ));
        break;
      case ManiphestTransaction::TYPE_OWNER:
        // If this is a no-op update, don't expand it.
        $old_value = $object->getOwnerPHID();
        $new_value = $xaction->getNewValue();
        if ($old_value === $new_value) {
          continue;
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

  private function loadProjectColumn($column_phid) {
    return id(new PhabricatorProjectColumnQuery())
      ->setViewer($this->getActor())
      ->withPHIDs(array($column_phid))
      ->executeOne();
  }

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $phids = parent::extractFilePHIDsFromCustomTransaction($object, $xaction);

    switch ($xaction->getTransactionType()) {
      case ManiphestTransaction::TYPE_COVER_IMAGE:
        $phids[] = $xaction->getNewValue();
        break;
    }

    return $phids;
  }


}

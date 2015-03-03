<?php

final class PhabricatorSearchAttachController
  extends PhabricatorSearchBaseController {

  private $phid;
  private $type;
  private $action;

  const ACTION_ATTACH       = 'attach';
  const ACTION_MERGE        = 'merge';
  const ACTION_DEPENDENCIES = 'dependencies';
  const ACTION_BLOCKS       = 'blocks';
  const ACTION_EDGE         = 'edge';

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->type = $data['type'];
    $this->action = idx($data, 'action', self::ACTION_ATTACH);
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($this->phid))
      ->executeOne();

    $object_type = $handle->getType();
    $attach_type = $this->type;

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($user)
      ->withPHIDs(array($this->phid))
      ->executeOne();

    if (!$object) {
      return new Aphront404Response();
    }

    $edge_type = null;
    switch ($this->action) {
      case self::ACTION_EDGE:
      case self::ACTION_DEPENDENCIES:
      case self::ACTION_BLOCKS:
      case self::ACTION_ATTACH:
        $edge_type = $this->getEdgeType($object_type, $attach_type);
        break;
    }

    if ($request->isFormPost()) {
      $phids = explode(';', $request->getStr('phids'));
      $phids = array_filter($phids);
      $phids = array_values($phids);

      if ($edge_type) {
        if (!$object instanceof PhabricatorApplicationTransactionInterface) {
          throw new Exception(
            pht(
              'Expected object ("%s") to implement interface "%s".',
              get_class($object),
              'PhabricatorApplicationTransactionInterface'));
        }

        $old_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
          $this->phid,
          $edge_type);
        $add_phids = $phids;
        $rem_phids = array_diff($old_phids, $add_phids);

        $txn_editor = $object->getApplicationTransactionEditor()
          ->setActor($user)
          ->setContentSourceFromRequest($request)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true);

        $txn_template = $object->getApplicationTransactionTemplate()
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $edge_type)
          ->setNewValue(array(
            '+' => array_fuse($add_phids),
            '-' => array_fuse($rem_phids),
          ));

        try {
          $txn_editor->applyTransactions(
            $object->getApplicationTransactionObject(),
            array($txn_template));
        } catch (PhabricatorEdgeCycleException $ex) {
          $this->raiseGraphCycleException($ex);
        }

        return id(new AphrontReloadResponse())->setURI($handle->getURI());
      } else {
        return $this->performMerge($object, $handle, $phids);
      }
    } else {
      if ($edge_type) {
        $phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
          $this->phid,
          $edge_type);
      } else {
        // This is a merge.
        $phids = array();
      }
    }

    $strings = $this->getStrings();

    $handles = $this->loadViewerHandles($phids);

    $obj_dialog = new PhabricatorObjectSelectorDialog();
    $obj_dialog
      ->setUser($user)
      ->setHandles($handles)
      ->setFilters($this->getFilters($strings))
      ->setSelectedFilter($strings['selected'])
      ->setExcluded($this->phid)
      ->setCancelURI($handle->getURI())
      ->setSearchURI('/search/select/'.$attach_type.'/')
      ->setTitle($strings['title'])
      ->setHeader($strings['header'])
      ->setButtonText($strings['button'])
      ->setInstructions($strings['instructions']);

    $dialog = $obj_dialog->buildDialog();

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function performMerge(
    ManiphestTask $task,
    PhabricatorObjectHandle $handle,
    array $phids) {

    $user = $this->getRequest()->getUser();
    $response = id(new AphrontReloadResponse())->setURI($handle->getURI());

    $phids = array_fill_keys($phids, true);
    unset($phids[$task->getPHID()]); // Prevent merging a task into itself.

    if (!$phids) {
      return $response;
    }

    $targets = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withPHIDs(array_keys($phids))
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true)
      ->execute();

    if (empty($targets)) {
      return $response;
    }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($user)
      ->setContentSourceFromRequest($this->getRequest())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $cc_vector = array();
    // since we loaded this via a generic object query, go ahead and get the
    // attach the subscriber and project phids now
    $task->attachSubscriberPHIDs(
      PhabricatorSubscribersQuery::loadSubscribersForPHID($task->getPHID()));
    $task->attachProjectPHIDs(
      PhabricatorEdgeQuery::loadDestinationPHIDs($task->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST));

    $cc_vector[] = $task->getSubscriberPHIDs();
    foreach ($targets as $target) {
      $cc_vector[] = $target->getSubscriberPHIDs();
      $cc_vector[] = array(
        $target->getAuthorPHID(),
        $target->getOwnerPHID(),
      );

      $merged_into_txn = id(new ManiphestTransaction())
        ->setTransactionType(ManiphestTransaction::TYPE_MERGED_INTO)
        ->setNewValue($task->getPHID());

      $editor->applyTransactions(
        $target,
        array($merged_into_txn));

    }
    $all_ccs = array_mergev($cc_vector);
    $all_ccs = array_filter($all_ccs);
    $all_ccs = array_unique($all_ccs);

    $add_ccs = id(new ManiphestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
      ->setNewValue(array('=' => $all_ccs));

    $merged_from_txn = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_MERGED_FROM)
      ->setNewValue(mpull($targets, 'getPHID'));

    $editor->applyTransactions(
      $task,
      array($add_ccs, $merged_from_txn));

    return $response;
  }

  private function getStrings() {
    switch ($this->type) {
      case DifferentialRevisionPHIDType::TYPECONST:
        $noun = 'Revisions';
        $selected = 'created';
        break;
      case ManiphestTaskPHIDType::TYPECONST:
        $noun = 'Tasks';
        $selected = 'assigned';
        break;
      case PhabricatorRepositoryCommitPHIDType::TYPECONST:
        $noun = 'Commits';
        $selected = 'created';
        break;
      case PholioMockPHIDType::TYPECONST:
        $noun = 'Mocks';
        $selected = 'created';
        break;
    }

    switch ($this->action) {
      case self::ACTION_EDGE:
      case self::ACTION_ATTACH:
        $dialog_title = "Manage Attached {$noun}";
        $header_text = "Currently Attached {$noun}";
        $button_text = "Save {$noun}";
        $instructions = null;
        break;
      case self::ACTION_MERGE:
        $dialog_title = 'Merge Duplicate Tasks';
        $header_text = 'Tasks To Merge';
        $button_text = "Merge {$noun}";
        $instructions =
          'These tasks will be merged into the current task and then closed. '.
          'The current task will grow stronger.';
        break;
      case self::ACTION_DEPENDENCIES:
        $dialog_title = 'Edit Dependencies';
        $header_text = 'Current Dependencies';
        $button_text = 'Save Dependencies';
        $instructions = null;
        break;
      case self::ACTION_BLOCKS:
        $dialog_title = pht('Edit Blocking Tasks');
        $header_text = pht('Current Blocking Tasks');
        $button_text = pht('Save Blocking Tasks');
        $instructions = null;
        break;
    }

    return array(
      'target_plural_noun'    => $noun,
      'selected'              => $selected,
      'title'                 => $dialog_title,
      'header'                => $header_text,
      'button'                => $button_text,
      'instructions'          => $instructions,
    );
  }

  private function getFilters(array $strings) {
    if ($this->type == PholioMockPHIDType::TYPECONST) {
      $filters = array(
        'created' => 'Created By Me',
        'all' => 'All '.$strings['target_plural_noun'],
      );
    } else {
      $filters = array(
        'assigned' => 'Assigned to Me',
        'created' => 'Created By Me',
        'open' => 'All Open '.$strings['target_plural_noun'],
        'all' => 'All '.$strings['target_plural_noun'],
      );
    }

    return $filters;
  }

  private function getEdgeType($src_type, $dst_type) {
    $t_cmit = PhabricatorRepositoryCommitPHIDType::TYPECONST;
    $t_task = ManiphestTaskPHIDType::TYPECONST;
    $t_drev = DifferentialRevisionPHIDType::TYPECONST;
    $t_mock = PholioMockPHIDType::TYPECONST;

    $map = array(
      $t_cmit => array(
        $t_task => DiffusionCommitHasTaskEdgeType::EDGECONST,
      ),
      $t_task => array(
        $t_cmit => ManiphestTaskHasCommitEdgeType::EDGECONST,
        $t_task => ManiphestTaskDependsOnTaskEdgeType::EDGECONST,
        $t_drev => ManiphestTaskHasRevisionEdgeType::EDGECONST,
        $t_mock => ManiphestTaskHasMockEdgeType::EDGECONST,
      ),
      $t_drev => array(
        $t_drev => DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST,
        $t_task => DifferentialRevisionHasTaskEdgeType::EDGECONST,
      ),
      $t_mock => array(
        $t_task => PholioMockHasTaskEdgeType::EDGECONST,
      ),
    );

    if (empty($map[$src_type][$dst_type])) {
      return null;
    }

    return $map[$src_type][$dst_type];
  }

  private function raiseGraphCycleException(PhabricatorEdgeCycleException $ex) {
    $cycle = $ex->getCycle();

    $handles = $this->loadViewerHandles($cycle);
    $names = array();
    foreach ($cycle as $cycle_phid) {
      $names[] = $handles[$cycle_phid]->getFullName();
    }
    $names = implode(" \xE2\x86\x92 ", $names);
    throw new Exception(
      "You can not create that dependency, because it would create a ".
      "circular dependency: {$names}.");
  }

}

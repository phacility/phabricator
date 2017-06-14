<?php

final class ManiphestTaskEditBulkJobType
   extends PhabricatorWorkerBulkJobType {

  public function getBulkJobTypeKey() {
    return 'maniphest.task.edit';
  }

  public function getJobName(PhabricatorWorkerBulkJob $job) {
    return pht('Maniphest Bulk Edit');
  }

  public function getDescriptionForConfirm(PhabricatorWorkerBulkJob $job) {
    return pht(
      'You are about to apply a bulk edit to Maniphest which will affect '.
      '%s task(s).',
      new PhutilNumber($job->getSize()));
  }

  public function getJobSize(PhabricatorWorkerBulkJob $job) {
    return count($job->getParameter('taskPHIDs', array()));
  }

  public function getDoneURI(PhabricatorWorkerBulkJob $job) {
    return $job->getParameter('doneURI');
  }

  public function createTasks(PhabricatorWorkerBulkJob $job) {
    $tasks = array();

    foreach ($job->getParameter('taskPHIDs', array()) as $phid) {
      $tasks[] = PhabricatorWorkerBulkTask::initializeNewTask($job, $phid);
    }

    return $tasks;
  }

  public function runTask(
    PhabricatorUser $actor,
    PhabricatorWorkerBulkJob $job,
    PhabricatorWorkerBulkTask $task) {

    $object = id(new ManiphestTaskQuery())
      ->setViewer($actor)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withPHIDs(array($task->getObjectPHID()))
      ->needProjectPHIDs(true)
      ->needSubscriberPHIDs(true)
      ->executeOne();
    if (!$object) {
      return;
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list->readFieldsFromStorage($object);

    $actions = $job->getParameter('actions');
    $xactions = $this->buildTransactions($actions, $object);

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($actor)
      ->setContentSource($job->newContentSource())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($object, $xactions);
  }

  private function buildTransactions($actions, ManiphestTask $task) {
    $value_map = array();
    $type_map = array(
      'add_comment' => PhabricatorTransactions::TYPE_COMMENT,
      'assign' => ManiphestTaskOwnerTransaction::TRANSACTIONTYPE,
      'status' => ManiphestTaskStatusTransaction::TRANSACTIONTYPE,
      'priority' => ManiphestTaskPriorityTransaction::TRANSACTIONTYPE,
      'add_project' => PhabricatorTransactions::TYPE_EDGE,
      'remove_project' => PhabricatorTransactions::TYPE_EDGE,
      'add_ccs' => PhabricatorTransactions::TYPE_SUBSCRIBERS,
      'remove_ccs' => PhabricatorTransactions::TYPE_SUBSCRIBERS,
      'space' => PhabricatorTransactions::TYPE_SPACE,
    );

    $edge_edit_types = array(
      'add_project'    => true,
      'remove_project' => true,
      'add_ccs'        => true,
      'remove_ccs'     => true,
    );

    $xactions = array();
    foreach ($actions as $action) {
      if (empty($type_map[$action['action']])) {
        throw new Exception(pht("Unknown batch edit action '%s'!", $action));
      }

      $type = $type_map[$action['action']];

      // Figure out the current value, possibly after modifications by other
      // batch actions of the same type. For example, if the user chooses to
      // "Add Comment" twice, we should add both comments. More notably, if the
      // user chooses "Remove Project..." and also "Add Project...", we should
      // avoid restoring the removed project in the second transaction.

      if (array_key_exists($type, $value_map)) {
        $current = $value_map[$type];
      } else {
        switch ($type) {
          case PhabricatorTransactions::TYPE_COMMENT:
            $current = null;
            break;
          case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
            $current = $task->getOwnerPHID();
            break;
          case ManiphestTaskStatusTransaction::TRANSACTIONTYPE:
            $current = $task->getStatus();
            break;
          case ManiphestTaskPriorityTransaction::TRANSACTIONTYPE:
            $current = $task->getPriority();
            break;
          case PhabricatorTransactions::TYPE_EDGE:
            $current = $task->getProjectPHIDs();
            break;
          case PhabricatorTransactions::TYPE_SUBSCRIBERS:
            $current = $task->getSubscriberPHIDs();
            break;
          case PhabricatorTransactions::TYPE_SPACE:
            $current = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
              $task);
            break;
        }
      }

      // Check if the value is meaningful / provided, and normalize it if
      // necessary. This discards, e.g., empty comments and empty owner
      // changes.

      $value = $action['value'];
      switch ($type) {
        case PhabricatorTransactions::TYPE_COMMENT:
          if (!strlen($value)) {
            continue 2;
          }
          break;
        case PhabricatorTransactions::TYPE_SPACE:
          if (empty($value)) {
            continue 2;
          }
          $value = head($value);
          break;
        case ManiphestTaskOwnerTransaction::TRANSACTIONTYPE:
          if (empty($value)) {
            continue 2;
          }
          $value = head($value);
          $no_owner = PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN;
          if ($value === $no_owner) {
            $value = null;
          }
          break;
        case PhabricatorTransactions::TYPE_EDGE:
          if (empty($value)) {
            continue 2;
          }
          break;
        case PhabricatorTransactions::TYPE_SUBSCRIBERS:
          if (empty($value)) {
            continue 2;
          }
          break;
      }

      // If the edit doesn't change anything, go to the next action. This
      // check is only valid for changes like "owner", "status", etc, not
      // for edge edits, because we should still apply an edit like
      // "Remove Projects: A, B" to a task with projects "A, B".

      if (empty($edge_edit_types[$action['action']])) {
        if ($value == $current) {
          continue;
        }
      }

      // Apply the value change; for most edits this is just replacement, but
      // some need to merge the current and edited values (add/remove project).

      switch ($type) {
        case PhabricatorTransactions::TYPE_COMMENT:
          if (strlen($current)) {
            $value = $current."\n\n".$value;
          }
          break;
        case PhabricatorTransactions::TYPE_EDGE:
          $is_remove = $action['action'] == 'remove_project';

          $current = array_fill_keys($current, true);
          $value   = array_fill_keys($value, true);

          $new = $current;
          $did_something = false;

          if ($is_remove) {
            foreach ($value as $phid => $ignored) {
              if (isset($new[$phid])) {
                unset($new[$phid]);
                $did_something = true;
              }
            }
          } else {
            foreach ($value as $phid => $ignored) {
              if (empty($new[$phid])) {
                $new[$phid] = true;
                $did_something = true;
              }
            }
          }

          if (!$did_something) {
            continue 2;
          }

          $value = array_keys($new);
          break;
        case PhabricatorTransactions::TYPE_SUBSCRIBERS:
          $is_remove = $action['action'] == 'remove_ccs';

          $current = array_fill_keys($current, true);

          $new = array();
          $did_something = false;

          if ($is_remove) {
            foreach ($value as $phid) {
              if (isset($current[$phid])) {
                $new[$phid] = true;
                $did_something = true;
              }
            }
            if ($new) {
              $value = array('-' => array_keys($new));
            }
          } else {
            $new = array();
            foreach ($value as $phid) {
              $new[$phid] = true;
              $did_something = true;
            }
            if ($new) {
              $value = array('+' => array_keys($new));
            }
          }
          if (!$did_something) {
            continue 2;
          }

          break;
      }

      $value_map[$type] = $value;
    }

    $template = new ManiphestTransaction();

    foreach ($value_map as $type => $value) {
      $xaction = clone $template;
      $xaction->setTransactionType($type);

      switch ($type) {
        case PhabricatorTransactions::TYPE_COMMENT:
          $xaction->attachComment(
            id(new ManiphestTransactionComment())
              ->setContent($value));
          break;
        case PhabricatorTransactions::TYPE_EDGE:
          $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
          $xaction
            ->setMetadataValue('edge:type', $project_type)
            ->setNewValue(
              array(
                '=' => array_fuse($value),
              ));
          break;
        case ManiphestTaskPriorityTransaction::TRANSACTIONTYPE:
          $keyword_map = ManiphestTaskPriority::getTaskPriorityKeywordsMap();
          $keyword = head(idx($keyword_map, $value));
          $xaction->setNewValue($keyword);
          break;
        default:
          $xaction->setNewValue($value);
          break;
      }

      $xactions[] = $xaction;
    }

    return $xactions;
  }
}

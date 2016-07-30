<?php

abstract class PhabricatorWorkerManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function getTaskSelectionArguments() {
    return array(
      array(
        'name' => 'id',
        'param' => 'id',
        'repeat' => true,
        'help' => pht('Select one or more tasks by ID.'),
      ),
      array(
        'name' => 'class',
        'param' => 'name',
        'help' => pht('Select all tasks of a given class.'),
      ),
    );
  }

  protected function loadTasks(PhutilArgumentParser $args) {
    $ids = $args->getArg('id');
    $class = $args->getArg('class');

    if (!$ids && !$class) {
      throw new PhutilArgumentUsageException(
        pht('Use --id or --class to select tasks.'));
    } if ($ids && $class) {
      throw new PhutilArgumentUsageException(
        pht('Use one of --id or --class to select tasks, but not both.'));
    }

    if ($ids) {
      $active_tasks = id(new PhabricatorWorkerActiveTask())->loadAllWhere(
        'id IN (%Ls)',
        $ids);
      $archive_tasks = id(new PhabricatorWorkerArchiveTaskQuery())
        ->withIDs($ids)
        ->execute();
    } else {
      $active_tasks = id(new PhabricatorWorkerActiveTask())->loadAllWhere(
        'taskClass IN (%Ls)',
        array($class));
      $archive_tasks = id(new PhabricatorWorkerArchiveTaskQuery())
        ->withClassNames(array($class))
        ->execute();
    }

    $tasks =
      mpull($active_tasks, null, 'getID') +
      mpull($archive_tasks, null, 'getID');

    if ($ids) {
      foreach ($ids as $id) {
        if (empty($tasks[$id])) {
          throw new PhutilArgumentUsageException(
            pht('No task exists with id "%s"!', $id));
        }
      }
    } else {
      if (!$tasks) {
        throw new PhutilArgumentUsageException(
          pht('No task exists with class "%s"!', $class));
      }
    }

    // When we lock tasks properly, this gets populated as a side effect. Just
    // fake it when doing manual CLI stuff. This makes sure CLI yields have
    // their expires times set properly.
    foreach ($tasks as $task) {
      if ($task instanceof PhabricatorWorkerActiveTask) {
        $task->setServerTime(PhabricatorTime::getNow());
      }
    }

    return $tasks;
  }

  protected function describeTask(PhabricatorWorkerTask $task) {
    return pht('Task %d (%s)', $task->getID(), $task->getTaskClass());
  }

}

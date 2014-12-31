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
    );
  }

  protected function loadTasks(PhutilArgumentParser $args) {
    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht('Use --id to select tasks by ID.'));
    }

    $active_tasks = id(new PhabricatorWorkerActiveTask())->loadAllWhere(
      'id IN (%Ls)',
      $ids);
    $archive_tasks = id(new PhabricatorWorkerArchiveTaskQuery())
      ->withIDs($ids)
      ->execute();

    $tasks =
      mpull($active_tasks, null, 'getID') +
      mpull($archive_tasks, null, 'getID');

    foreach ($ids as $id) {
      if (empty($tasks[$id])) {
        throw new PhutilArgumentUsageException(
          pht('No task exists with id "%s"!', $id));
      }
    }

    return $tasks;
  }

  protected function describeTask(PhabricatorWorkerTask $task) {
    return pht('Task %d (%s)', $task->getID(), $task->getTaskClass());
  }

}

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
      array(
        'name' => 'min-failure-count',
        'param' => 'int',
        'help' => pht('Limit to tasks with at least this many failures.'),
      ),
    );
  }

  protected function loadTasks(PhutilArgumentParser $args) {
    $ids = $args->getArg('id');
    $class = $args->getArg('class');
    $min_failures = $args->getArg('min-failure-count');

    if (!$ids && !$class && !$min_failures) {
      throw new PhutilArgumentUsageException(
        pht('Use --id, --class, or --min-failure-count to select tasks.'));
    }

    $active_query = new PhabricatorWorkerActiveTaskQuery();
    $archive_query = new PhabricatorWorkerArchiveTaskQuery();

    if ($ids) {
      $active_query = $active_query->withIDs($ids);
      $archive_query = $archive_query->withIDs($ids);
    }

    if ($class) {
      $class_array = array($class);
      $active_query = $active_query->withClassNames($class_array);
      $archive_query = $archive_query->withClassNames($class_array);
    }

    if ($min_failures) {
      $active_query = $active_query->withFailureCountBetween(
        $min_failures, null);
      $archive_query = $archive_query->withFailureCountBetween(
        $min_failures, null);
    }

    $active_tasks = $active_query->execute();
    $archive_tasks = $archive_query->execute();
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
    }
    if ($class && $min_failures) {
      if (!$tasks) {
        throw new PhutilArgumentUsageException(
          pht('No task exists with class "%s" and at least %d failures!',
            $class,
            $min_failures));
      }
    } else if ($class) {
      if (!$tasks) {
        throw new PhutilArgumentUsageException(
          pht('No task exists with class "%s"!', $class));
      }
    } else if ($min_failures) {
      if (!$tasks) {
        throw new PhutilArgumentUsageException(
          pht('No tasks exist with at least %d failures!', $min_failures));
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

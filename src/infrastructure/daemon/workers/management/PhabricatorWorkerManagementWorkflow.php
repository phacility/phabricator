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
        'help' => pht('Select tasks of a given class.'),
      ),
      array(
        'name' => 'min-failure-count',
        'param' => 'int',
        'help' => pht('Select tasks with a minimum failure count.'),
      ),
      array(
        'name' => 'max-failure-count',
        'param' => 'int',
        'help' => pht('Select tasks with a maximum failure count.'),
      ),
      array(
        'name' => 'active',
        'help' => pht('Select active tasks.'),
      ),
      array(
        'name' => 'archived',
        'help' => pht('Select archived tasks.'),
      ),
      array(
        'name' => 'container',
        'param' => 'name',
        'help' => pht(
          'Select tasks with the given container or containers.'),
        'repeat' => true,
      ),
      array(
        'name' => 'object',
        'param' => 'name',
        'repeat' => true,
        'help' => pht(
          'Select tasks affecting the given object or objects.'),
      ),
      array(
        'name' => 'min-priority',
        'param' => 'int',
        'help' => pht('Select tasks with a minimum priority.'),
      ),
      array(
        'name' => 'max-priority',
        'param' => 'int',
        'help' => pht('Select tasks with a maximum priority.'),
      ),
      array(
        'name' => 'limit',
        'param' => 'int',
        'help' => pht('Limit selection to a maximum number of tasks.'),
      ),
    );
  }

  protected function loadTasks(PhutilArgumentParser $args) {
    $ids = $args->getArg('id');
    $class = $args->getArg('class');
    $active = $args->getArg('active');
    $archived = $args->getArg('archived');

    $container_names = $args->getArg('container');
    $object_names = $args->getArg('object');

    $min_failures = $args->getArg('min-failure-count');
    $max_failures = $args->getArg('max-failure-count');

    $min_priority = $args->getArg('min-priority');
    $max_priority = $args->getArg('max-priority');

    $limit = $args->getArg('limit');

    $any_constraints = false;
    if ($ids) {
      $any_constraints = true;
    }

    if ($class) {
      $any_constraints = true;
    }

    if ($active || $archived) {
      $any_constraints = true;
      if ($active && $archived) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not specify both "--active" and "--archived" tasks: '.
            'no tasks can match both constraints.'));
      }
    }

    if ($container_names) {
      $any_constraints = true;
      $container_phids = $this->loadObjectPHIDsFromArguments($container_names);
    } else {
      $container_phids = array();
    }

    if ($object_names) {
      $any_constraints = true;
      $object_phids = $this->loadObjectPHIDsFromArguments($object_names);
    } else {
      $object_phids = array();
    }

    if (($min_failures !== null) || ($max_failures !== null)) {
      $any_constraints = true;
      if (($min_failures !== null) && ($max_failures !== null)) {
        if ($min_failures > $max_failures) {
          throw new PhutilArgumentUsageException(
            pht(
              'Specified "--min-failures" must not be larger than '.
              'specified "--max-failures".'));
        }
      }
    }

    if (($min_priority !== null) || ($max_priority !== null)) {
      $any_constraints = true;
      if (($min_priority !== null) && ($max_priority !== null)) {
        if ($min_priority > $max_priority) {
          throw new PhutilArgumentUsageException(
            pht(
              'Specified "--min-priority" may not be larger than '.
              'specified "--max-priority".'));
        }
      }
    }

    if (!$any_constraints) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use constraint flags (like "--id" or "--class") to select which '.
          'tasks to affect. Use "--help" for a list of supported constraint '.
          'flags.'));
    }

    if ($limit !== null) {
      $limit = (int)$limit;
      if ($limit <= 0) {
        throw new PhutilArgumentUsageException(
          pht(
            'Specified "--limit" must be a positive integer.'));
      }
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
      $active_query->withFailureCountBetween($min_failures, $max_failures);
      $archive_query->withFailureCountBetween($min_failures, $max_failures);
    }

    if ($container_phids) {
      $active_query->withContainerPHIDs($container_phids);
      $archive_query->withContainerPHIDs($container_phids);
    }

    if ($object_phids) {
      $active_query->withObjectPHIDs($object_phids);
      $archive_query->withObjectPHIDs($object_phids);
    }

    if ($min_priority || $max_priority) {
      $active_query->withPriorityBetween($min_priority, $max_priority);
      $archive_query->withPriorityBetween($min_priority, $max_priority);
    }

    if ($limit) {
      $active_query->setLimit($limit);
      $archive_query->setLimit($limit);
    }

    if ($archived) {
      $active_tasks = array();
    } else {
      $active_tasks = $active_query->execute();
    }

    if ($active) {
      $archive_tasks = array();
    } else {
      $archive_tasks = $archive_query->execute();
    }

    $tasks =
      mpull($active_tasks, null, 'getID') +
      mpull($archive_tasks, null, 'getID');

    if ($limit) {
      $tasks = array_slice($tasks, 0, $limit, $preserve_keys = true);
    }


    if ($ids) {
      foreach ($ids as $id) {
        if (empty($tasks[$id])) {
          throw new PhutilArgumentUsageException(
            pht('No task with ID "%s" matches the constraints!', $id));
        }
      }
    }

    // We check that IDs are valid, but for all other constraints it is
    // acceptable to select no tasks to act upon.

    // When we lock tasks properly, this gets populated as a side effect. Just
    // fake it when doing manual CLI stuff. This makes sure CLI yields have
    // their expires times set properly.
    foreach ($tasks as $task) {
      if ($task instanceof PhabricatorWorkerActiveTask) {
        $task->setServerTime(PhabricatorTime::getNow());
      }
    }

    // If the user specified one or more "--id" flags, process the tasks in
    // the given order. Otherwise, process them in FIFO order so the sequence
    // is somewhat consistent with natural execution order.

    // NOTE: When "--limit" is used, we end up selecting the newest tasks
    // first. At time of writing, there's no way to order the queries
    // correctly, so just accept it as reasonable behavior.

    if ($ids) {
      $tasks = array_select_keys($tasks, $ids);
    } else {
      $tasks = msort($tasks, 'getID');
    }

    return $tasks;
  }

  protected function describeTask(PhabricatorWorkerTask $task) {
    return pht('Task %d (%s)', $task->getID(), $task->getTaskClass());
  }

  private function loadObjectPHIDsFromArguments(array $names) {
    $viewer = $this->getViewer();

    $seen_names = array();
    foreach ($names as $name) {
      if (isset($seen_names[$name])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Object "%s" is specified more than once. Specify only unique '.
            'objects.',
            $name));
      }
      $seen_names[$name] = true;
    }

    $object_query = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($names);

    $object_query->execute();

    $name_map = $object_query->getNamedResults();
    $phid_map = array();
    foreach ($names as $name) {
      if (!isset($name_map[$name])) {
        throw new PhutilArgumentUsageException(
          pht(
            'No object with name "%s" could be loaded.',
            $name));
      }

      $phid = $name_map[$name]->getPHID();

      if (isset($phid_map[$phid])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Names "%s" and "%s" identify the same object. Specify only '.
            'unique objects.',
            $name,
            $phid_map[$phid]));
      }

      $phid_map[$phid] = $name;
    }

    return array_keys($phid_map);
  }

}

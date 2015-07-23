<?php

final class ManiphestTaskTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testTaskReordering() {
    $viewer = $this->generateNewTestUser();

    $t1 = $this->newTask($viewer, pht('Task 1'));
    $t2 = $this->newTask($viewer, pht('Task 2'));
    $t3 = $this->newTask($viewer, pht('Task 3'));

    $auto_base = min(mpull(array($t1, $t2, $t3), 'getID'));


    // Default order should be reverse creation.
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(3, 2, 1), array_keys($tasks));


    // Move T3 to the middle.
    $this->moveTask($viewer, $t3, $t2, true);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 3, 1), array_keys($tasks));


    // Move T3 to the end.
    $this->moveTask($viewer, $t3, $t1, true);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 1, 3), array_keys($tasks));


    // Repeat the move above, there should be no overall change in order.
    $this->moveTask($viewer, $t3, $t1, true);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 1, 3), array_keys($tasks));


    // Move T3 to the first slot in the priority.
    $this->movePriority($viewer, $t3, $t3->getPriority(), false);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(3, 2, 1), array_keys($tasks));


    // Move T3 to the last slot in the priority.
    $this->movePriority($viewer, $t3, $t3->getPriority(), true);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 1, 3), array_keys($tasks));


    // Move T3 before T2.
    $this->moveTask($viewer, $t3, $t2, false);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(3, 2, 1), array_keys($tasks));


    // Move T3 before T1.
    $this->moveTask($viewer, $t3, $t1, false);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 3, 1), array_keys($tasks));

  }

  public function testTaskAdjacentBlocks() {
    $viewer = $this->generateNewTestUser();

    $t = array();
    for ($ii = 1; $ii < 10; $ii++) {
      $t[$ii] = $this->newTask($viewer, pht('Task Block %d', $ii));

      // This makes sure this test remains meaningful if we begin assigning
      // subpriorities when tasks are created.
      $t[$ii]->setSubpriority(0)->save();
    }

    $auto_base = min(mpull($t, 'getID'));

    $tasks = $this->loadTasks($viewer, $auto_base);
    $this->assertEqual(
      array(9, 8, 7, 6, 5, 4, 3, 2, 1),
      array_keys($tasks));

    $this->moveTask($viewer, $t[9], $t[8], true);
    $tasks = $this->loadTasks($viewer, $auto_base);
    $this->assertEqual(
      array(8, 9, 7, 6, 5, 4, 3, 2, 1),
      array_keys($tasks));

    // When there is a large block of tasks which all have the same
    // subpriority, they should be assigned distinct subpriorities as a
    // side effect of having a task moved into the block.

    $subpri = mpull($tasks, 'getSubpriority');
    $unique_subpri = array_unique($subpri);
    $this->assertEqual(
      9,
      count($subpri),
      pht('Expected subpriorities to be distributed.'));
  }

  private function newTask(PhabricatorUser $viewer, $title) {
    $task = ManiphestTask::initializeNewTask($viewer);

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_TITLE)
      ->setNewValue($title);


    $this->applyTaskTransactions($viewer, $task, $xactions);

    return $task;
  }

  private function loadTasks(PhabricatorUser $viewer, $auto_base) {
    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->setOrder(ManiphestTaskQuery::ORDER_PRIORITY)
      ->execute();

    // NOTE: AUTO_INCREMENT changes survive ROLLBACK, and we can't throw them
    // away without committing the current transaction, so we adjust the
    // apparent task IDs as though the first one had been ID 1. This makes the
    // tests a little easier to understand.

    $map = array();
    foreach ($tasks as $task) {
      $map[($task->getID() - $auto_base) + 1] = $task;
    }

    return $map;
  }

  private function moveTask(PhabricatorUser $viewer, $src, $dst, $is_after) {
    list($pri, $sub) = ManiphestTransactionEditor::getAdjacentSubpriority(
      $dst,
      $is_after);

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_PRIORITY)
      ->setNewValue($pri);

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_SUBPRIORITY)
      ->setNewValue($sub);

    return $this->applyTaskTransactions($viewer, $src, $xactions);
  }

  private function movePriority(
    PhabricatorUser $viewer,
    $src,
    $target_priority,
    $is_end) {

    list($pri, $sub) = ManiphestTransactionEditor::getEdgeSubpriority(
      $target_priority,
      $is_end);

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_PRIORITY)
      ->setNewValue($pri);

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_SUBPRIORITY)
      ->setNewValue($sub);

    return $this->applyTaskTransactions($viewer, $src, $xactions);
  }

  private function applyTaskTransactions(
    PhabricatorUser $viewer,
    ManiphestTask $task,
    array $xactions) {

    $content_source = PhabricatorContentSource::newConsoleSource();

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->applyTransactions($task, $xactions);

    return $task;
  }

}

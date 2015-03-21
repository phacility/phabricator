<?php

final class ManiphestTaskTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testTaskReordering() {
    $viewer = $this->generateNewTestUser();

    $t1 = $this->newTask($viewer, 'Task 1');
    $t2 = $this->newTask($viewer, 'Task 2');
    $t3 = $this->newTask($viewer, 'Task 3');


    // Default order should be reverse creation.
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(3, 2, 1), array_keys($tasks));


    // Move T3 to the middle.
    $this->moveTask($viewer, $t3, $t2, true);
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 3, 1), array_keys($tasks));


    // Move T3 to the end.
    $this->moveTask($viewer, $t3, $t1, true);
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 1, 3), array_keys($tasks));


    // Repeat the move above, there should be no overall change in order.
    $this->moveTask($viewer, $t3, $t1, true);
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 1, 3), array_keys($tasks));


    // Move T3 to the first slot in the priority.
    $this->movePriority($viewer, $t3, $t3->getPriority(), false);
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(3, 2, 1), array_keys($tasks));


    // Move T3 to the last slot in the priority.
    $this->movePriority($viewer, $t3, $t3->getPriority(), true);
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 1, 3), array_keys($tasks));


    // Move T3 before T2.
    $this->moveTask($viewer, $t3, $t2, false);
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(3, 2, 1), array_keys($tasks));


    // Move T3 before T1.
    $this->moveTask($viewer, $t3, $t1, false);
    $tasks = $this->loadTasks($viewer);
    $t1 = $tasks[1];
    $t2 = $tasks[2];
    $t3 = $tasks[3];
    $this->assertEqual(array(2, 3, 1), array_keys($tasks));

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

  private function loadTasks(PhabricatorUser $viewer) {
    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->execute();

    $tasks = mpull($tasks, null, 'getID');

    return $tasks;
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

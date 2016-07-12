<?php

final class PhabricatorDaemonTasksTableView extends AphrontView {

  private $tasks;
  private $noDataString;

  public function setTasks(array $tasks) {
    $this->tasks = $tasks;
    return $this;
  }

  public function getTasks() {
    return $this->tasks;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function render() {
    $tasks = $this->getTasks();

    $rows = array();
    foreach ($tasks as $task) {
      $rows[] = array(
        $task->getID(),
        $task->getTaskClass(),
        $task->getLeaseOwner(),
        $task->getLeaseExpires()
          ? phutil_format_relative_time($task->getLeaseExpires() - time())
          : '-',
        $task->getPriority(),
        $task->getFailureCount(),
        phutil_tag(
          'a',
          array(
            'href' => '/daemon/task/'.$task->getID().'/',
            'class' => 'button small grey',
          ),
          pht('View Task')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('ID'),
        pht('Class'),
        pht('Owner'),
        pht('Expires'),
        pht('Priority'),
        pht('Failures'),
        '',
      ));
    $table->setColumnClasses(
      array(
        'n',
        'wide',
        '',
        '',
        'n',
        'n',
        'action',
      ));

    if (strlen($this->getNoDataString())) {
      $table->setNoDataString($this->getNoDataString());
    }

    return $table;
  }

}

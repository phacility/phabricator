<?php

final class PhabricatorWorkerDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'workers';

  public function getExtensionName() {
    return pht('Worker Tasks');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $tasks = id(new PhabricatorWorkerActiveTask())->loadAllWhere(
      'objectPHID = %s',
      $object->getPHID());

    foreach ($tasks as $task) {
      $task->archiveTask(
        PhabricatorWorkerArchiveTask::RESULT_CANCELLED,
        0);
    }
  }

}

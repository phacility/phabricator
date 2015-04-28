<?php

final class ManiphestInfoConduitAPIMethod extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.info';
  }

  public function getMethodDescription() {
    return 'Retrieve information about a Maniphest task, given its id.';
  }

  protected function defineParamTypes() {
    return array(
      'task_id' => 'required id',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_TASK' => 'No such maniphest task exists',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task_id = $request->getValue('task_id');

    $task = id(new ManiphestTaskQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($task_id))
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true)
      ->executeOne();
    if (!$task) {
      throw new ConduitException('ERR_BAD_TASK');
    }

    return $this->buildTaskInfoDictionary($task);
  }

}

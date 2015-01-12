<?php

final class ManiphestCreateTaskConduitAPIMethod
  extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.createtask';
  }

  public function getMethodDescription() {
    return 'Create a new Maniphest task.';
  }

  public function defineParamTypes() {
    return $this->getTaskFields($is_new = true);
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => 'Missing or malformed parameter.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task = ManiphestTask::initializeNewTask($request->getUser());

    $task = $this->applyRequest($task, $request, $is_new = true);

    return $this->buildTaskInfoDictionary($task);
  }

}

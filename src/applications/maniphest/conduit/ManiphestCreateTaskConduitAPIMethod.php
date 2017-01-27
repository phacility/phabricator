<?php

final class ManiphestCreateTaskConduitAPIMethod
  extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.createtask';
  }

  public function getMethodDescription() {
    return pht('Create a new Maniphest task.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "maniphest.edit" instead.');
  }

  protected function defineParamTypes() {
    return $this->getTaskFields($is_new = true);
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task = ManiphestTask::initializeNewTask($request->getUser());

    $task = $this->applyRequest($task, $request, $is_new = true);

    return $this->buildTaskInfoDictionary($task);
  }

}

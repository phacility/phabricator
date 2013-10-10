<?php

/**
 * @group conduit
 */
final class ConduitAPI_maniphest_createtask_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Create a new Maniphest task.";
  }

  public function defineParamTypes() {
    return $this->getTaskFields($is_new = true);
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => 'Missing or malformed parameter.'
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task = ManiphestTask::initializeNewTask($request->getUser());

    $this->applyRequest($task, $request, $is_new = true);

    return $this->buildTaskInfoDictionary($task);
  }

}

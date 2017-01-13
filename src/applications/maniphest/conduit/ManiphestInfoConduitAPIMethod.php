<?php

final class ManiphestInfoConduitAPIMethod extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.info';
  }

  public function getMethodDescription() {
    return pht('Retrieve information about a Maniphest task, given its ID.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "maniphest.search" instead.');
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
      'ERR_BAD_TASK' => pht('No such Maniphest task exists.'),
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

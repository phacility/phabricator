<?php

final class ManiphestUpdateConduitAPIMethod extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.update';
  }

  public function getMethodDescription() {
    return pht('Update an existing Maniphest task.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "maniphest.edit" instead.');
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-TASK'          => pht('No such Maniphest task exists.'),
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
      'ERR-NO-EFFECT'         => pht('Update has no effect.'),
    );
  }

  protected function defineParamTypes() {
    return $this->getTaskFields($is_new = false);
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('id');
    $phid = $request->getValue('phid');

    if (($id && $phid) || (!$id && !$phid)) {
      throw new Exception(
        pht(
          "Specify exactly one of '%s' and '%s'.",
          'id',
          'phid'));
    }

    $query = id(new ManiphestTaskQuery())
      ->setViewer($request->getUser())
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true);
    if ($id) {
      $query->withIDs(array($id));
    } else {
      $query->withPHIDs(array($phid));
    }
    $task = $query->executeOne();

    $params = $request->getAllParameters();
    unset($params['id']);
    unset($params['phid']);

    if (call_user_func_array('coalesce', $params) === null) {
      throw new ConduitException('ERR-NO-EFFECT');
    }

    if (!$task) {
      throw new ConduitException('ERR-BAD-TASK');
    }

    $task = $this->applyRequest($task, $request, $is_new = false);

    return $this->buildTaskInfoDictionary($task);
  }

}

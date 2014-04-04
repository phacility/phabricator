<?php

/**
 * @group conduit
 */
final class ConduitAPI_maniphest_update_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Update an existing Maniphest task.";
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-TASK'          => 'No such maniphest task exists.',
      'ERR-INVALID-PARAMETER' => 'Missing or malformed parameter.',
      'ERR-NO-EFFECT'         => 'Update has no effect.',
    );
  }

  public function defineParamTypes() {
    return $this->getTaskFields($is_new = false);
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('id');
    $phid = $request->getValue('phid');

    if (($id && $phid) || (!$id && !$phid)) {
      throw new Exception("Specify exactly one of 'id' and 'phid'.");
    }

    if ($id) {
      $task = id(new ManiphestTaskQuery())
        ->setViewer($request->getUser())
        ->withIDs(array($id))
        ->executeOne();
    } else {
      $task = id(new ManiphestTaskQuery())
        ->setViewer($request->getUser())
        ->withPHIDs(array($phid))
        ->executeOne();
    }

    $params = $request->getAllParameters();
    unset($params['id']);
    unset($params['phid']);

    if (call_user_func_array('coalesce', $params) === null) {
      throw new ConduitException('ERR-NO-EFFECT');
    }

    if (!$task) {
      throw new ConduitException('ERR-BAD-TASK');
    }

    $this->applyRequest($task, $request, $is_new = false);

    return $this->buildTaskInfoDictionary($task);
  }

}

<?php

final class ConduitAPI_releephwork_canpush_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Return whether the conduit user is allowed to push.";
  }

  public function defineParamTypes() {
    return array(
      'projectPHID' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'bool';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $releeph_project = id(new ReleephProject())
      ->loadOneWhere('phid = %s', $request->getValue('projectPHID'));

    if (!$releeph_project->getPushers()) {
      return true;
    } else {
      $user = $request->getUser();
      return $releeph_project->isPusher($user);
    }
  }
}

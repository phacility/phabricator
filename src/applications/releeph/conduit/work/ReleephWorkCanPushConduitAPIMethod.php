<?php

final class ReleephWorkCanPushConduitAPIMethod extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releephwork.canpush';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Return whether the conduit user is allowed to push.');
  }

  protected function defineParamTypes() {
    return array(
      'projectPHID' => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'bool';
  }

  protected function execute(ConduitAPIRequest $request) {
    $releeph_project = id(new ReleephProject())
      ->loadOneWhere('phid = %s', $request->getValue('projectPHID'));
    $user = $request->getUser();
    return $releeph_project->isAuthoritative($user);
  }

}

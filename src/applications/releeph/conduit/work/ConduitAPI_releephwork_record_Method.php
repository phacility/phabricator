<?php

final class ConduitAPI_releephwork_record_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Wrapper to ReleephRequestEditor->recordSuccessfulCommit().";
  }

  public function defineParamTypes() {
    return array(
      'requestPHID'       => 'required string',
      'action'            => 'required enum<"pick", "revert">',
      'commitIdentifier'  => 'required string',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $action = $request->getValue('action');
    $new_commit_id = $request->getValue('commitIdentifier');

    $releeph_request = id(new ReleephRequest())
      ->loadOneWhere('phid = %s', $request->getValue('requestPHID'));

    id(new ReleephRequestEditor($releeph_request))
      ->setActor($request->getUser())
      ->recordSuccessfulCommit($action, $new_commit_id);
  }

}

<?php

final class ConduitAPI_releephwork_recordpickstatus_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Wrapper to ReleephRequestEditor->changePickStatus().";
  }

  public function defineParamTypes() {
    return array(
      'requestPHID'       => 'required string',
      'action'            => 'required enum<"pick", "revert">',
      'ok'                => 'required bool',
      'dryRun'            => 'optional bool',
      'details'           => 'optional dict<string, wild>',
    );
  }

  public function defineReturnType() {
    return '';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $action = $request->getValue('action');
    $ok = $request->getValue('ok');
    $dry_run = $request->getValue('dryRun');
    $details = $request->getValue('details', array());

    switch ($request->getValue('action')) {
      case 'pick':
        $pick_status = $ok
          ? ReleephRequest::PICK_OK
          : ReleephRequest::PICK_FAILED;
        break;

      case 'revert':
        $pick_status = $ok
          ? ReleephRequest::REVERT_OK
          : ReleephRequest::REVERT_FAILED;
        break;

      default:
        throw new Exception("Unknown action {$action}!");
    }

    $releeph_request = id(new ReleephRequest())
      ->loadOneWhere('phid = %s', $request->getValue('requestPHID'));

    id(new ReleephRequestEditor($releeph_request))
      ->setActor($request->getUser())
      ->changePickStatus($pick_status, $dry_run, $details);
  }

}

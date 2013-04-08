<?php

final class ConduitAPI_releephwork_recordpickstatus_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Record whether a pick or revert was successful or not.";
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

    $editor = id(new ReleephRequestTransactionalEditor())
      ->setActor($request->getUser())
      ->setContinueOnNoEffect(true)
      ->setContentSource(
        PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_CONDUIT,
          array()));

    $xactions = array();

    $xactions[] = id(new ReleephRequestTransaction())
      ->setTransactionType(ReleephRequestTransaction::TYPE_PICK_STATUS)
      ->setMetadataValue('dryRun', $dry_run)
      ->setMetadataValue('details', $details)
      ->setNewValue($pick_status);

    $editor->applyTransactions($releeph_request, $xactions);
  }

}

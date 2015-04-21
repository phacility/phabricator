<?php

final class ReleephWorkRecordPickStatusConduitAPIMethod
  extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releephwork.recordpickstatus';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return 'Record whether a pick or revert was successful or not.';
  }

  protected function defineParamTypes() {
    $action_const = $this->formatStringConstants(
      array(
        'pick',
        'revert',
      ));

    return array(
      'requestPHID'       => 'required string',
      'action'            => 'required '.$action_const,
      'ok'                => 'required bool',
      'dryRun'            => 'optional bool',
      'details'           => 'optional dict<string, wild>',
    );
  }

  protected function defineReturnType() {
    return '';
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

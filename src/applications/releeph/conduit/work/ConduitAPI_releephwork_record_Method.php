<?php

final class ConduitAPI_releephwork_record_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  /**
   * Record that a request was committed locally, and is about to be pushed to
   * the remote repository.
   *
   * This lets us mark a ReleephRequest as being in a branch in real time so
   * that no one else tries to pick it.
   *
   * When the daemons discover this commit in the repository with
   * DifferentialReleephRequestFieldSpecification, we'll be able to record the
   * commit's PHID as well.  That process is slow though, and we don't want to
   * wait a whole minute before marking something as cleanly picked or
   * reverted.
   */
  public function getMethodDescription() {
    return 'Record whether we committed a pick or revert '.
      'to the upstream repository.';
  }

  public function defineParamTypes() {
    $action_const = $this->formatStringConstants(
      array(
        'pick',
        'revert',
      ));

    return array(
      'requestPHID'       => 'required string',
      'action'            => 'required '.$action_const,
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

    $xactions = array();

    $xactions[] = id(new ReleephRequestTransaction())
      ->setTransactionType(ReleephRequestTransaction::TYPE_COMMIT)
      ->setMetadataValue('action', $action)
      ->setNewValue($new_commit_id);

    $editor = id(new ReleephRequestTransactionalEditor())
      ->setActor($request->getUser())
      ->setContinueOnNoEffect(true)
      ->setContentSource(
        PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_CONDUIT,
          array()));

    $editor->applyTransactions($releeph_request, $xactions);
  }

}

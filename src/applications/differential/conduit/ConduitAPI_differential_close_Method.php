<?php

final class ConduitAPI_differential_close_Method
  extends ConduitAPI_differential_Method {

  public function getMethodDescription() {
    return pht('Close a Differential revision.');
  }

  public function defineParamTypes() {
    return array(
      'revisionID' => 'required int',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'Revision was not found.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $id = $request->getValue('revisionID');

    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($id))
      ->setViewer($viewer)
      ->needReviewerStatus(true)
      ->executeOne();
    if (!$revision) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    $xactions = array();
    $xactions[] = id(new DifferentialTransaction())
      ->setTransactionType(DifferentialTransaction::TYPE_ACTION)
      ->setNewValue(DifferentialAction::ACTION_CLOSE);

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_CONDUIT,
      array());

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setContentSourceFromConduitRequest($request)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true);

    $editor->applyTransactions($revision, $xactions);

    return;
  }

}

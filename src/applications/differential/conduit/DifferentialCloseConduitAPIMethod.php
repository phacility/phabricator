<?php

final class DifferentialCloseConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.close';
  }

  public function getMethodDescription() {
    return pht('Close a Differential revision.');
  }

  protected function defineParamTypes() {
    return array(
      'revisionID' => 'required int',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => pht('Revision was not found.'),
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

    $content_source = $request->newContentSource();

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($request->newContentSource())
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true);

    $editor->applyTransactions($revision, $xactions);

    return;
  }

}

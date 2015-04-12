<?php

final class DifferentialCreateCommentConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.createcomment';
  }

  public function getMethodDescription() {
    return pht('Add a comment to a Differential revision.');
  }

  protected function defineParamTypes() {
    return array(
      'revision_id'    => 'required revisionid',
      'message'        => 'optional string',
      'action'         => 'optional string',
      'silent'         => 'optional bool',
      'attach_inlines' => 'optional bool',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION' => 'Bad revision ID.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getValue('revision_id')))
      ->needReviewerStatus(true)
      ->needReviewerAuthority(true)
      ->executeOne();
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    $xactions = array();

    $action = $request->getValue('action');
    if ($action && ($action != 'comment') && ($action != 'none')) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType(DifferentialTransaction::TYPE_ACTION)
        ->setNewValue($action);
    }

    $content = $request->getValue('message');
    if (strlen($content)) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new DifferentialTransactionComment())
            ->setContent($content));
    }

    if ($request->getValue('attach_inlines')) {
      $type_inline = DifferentialTransaction::TYPE_INLINE;
      $inlines = DifferentialTransactionQuery::loadUnsubmittedInlineComments(
        $viewer,
        $revision);
      foreach ($inlines as $inline) {
        $xactions[] = id(new DifferentialTransaction())
          ->setTransactionType($type_inline)
          ->attachComment($inline);
      }
    }

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setDisableEmail($request->getValue('silent'))
      ->setContentSourceFromConduitRequest($request)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $editor->applyTransactions($revision, $xactions);

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}

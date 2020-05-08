<?php

final class DifferentialCreateCommentConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.createcomment';
  }

  public function getMethodDescription() {
    return pht('Add a comment to a Differential revision.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "differential.revision.edit" instead.');
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
      'ERR_BAD_REVISION' => pht('Bad revision ID.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getValue('revision_id')))
      ->needReviewers(true)
      ->needReviewerAuthority(true)
      ->needActiveDiffs(true)
      ->executeOne();
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    $xactions = array();

    $modular_map = array(
      'accept' => DifferentialRevisionAcceptTransaction::TRANSACTIONTYPE,
      'reject' => DifferentialRevisionRejectTransaction::TRANSACTIONTYPE,
      'resign' => DifferentialRevisionResignTransaction::TRANSACTIONTYPE,
      'request_review' =>
        DifferentialRevisionRequestReviewTransaction::TRANSACTIONTYPE,
      'rethink' => DifferentialRevisionPlanChangesTransaction::TRANSACTIONTYPE,
    );

    $action = $request->getValue('action');
    if (isset($modular_map[$action])) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($modular_map[$action])
        ->setNewValue(true);
    } else if ($action) {
      switch ($action) {
        case 'comment':
        case 'none':
          break;
        default:
          throw new Exception(
            pht(
              'Unsupported action "%s".',
              $action));
          break;
      }
    }

    $content = $request->getValue('message');
    if (strlen($content)) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new DifferentialTransactionComment())
            ->setContent($content));
    }

    // NOTE: The legacy "attach_inlines" flag is now ignored and has no
    // effect. See T13513.

    // NOTE: The legacy "silent" flag is now ignored and has no effect. See
    // T13042.

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($request->newContentSource())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $editor->applyTransactions($revision, $xactions);

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}

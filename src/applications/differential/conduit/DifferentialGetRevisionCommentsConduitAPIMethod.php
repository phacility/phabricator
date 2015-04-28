<?php

final class DifferentialGetRevisionCommentsConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.getrevisioncomments';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht('Obsolete and doomed, see T2222.');
  }

  public function getMethodDescription() {
    return 'Retrieve Differential Revision Comments.';
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
      'inlines' => 'optional bool (deprecated)',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $results = array();
    $revision_ids = $request->getValue('ids');

    if (!$revision_ids) {
      return $results;
    }

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs($revision_ids)
      ->execute();

    if (!$revisions) {
      return $results;
    }

    $xactions = id(new DifferentialTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(mpull($revisions, 'getPHID'))
      ->execute();

    $revisions = mpull($revisions, null, 'getPHID');

    foreach ($xactions as $xaction) {
      $revision = idx($revisions, $xaction->getObjectPHID());
      if (!$revision) {
        continue;
      }

      $type = $xaction->getTransactionType();
      if ($type == DifferentialTransaction::TYPE_ACTION) {
        $action = $xaction->getNewValue();
      } else if ($type == PhabricatorTransactions::TYPE_COMMENT) {
        $action = 'comment';
      } else {
        $action = 'none';
      }

      $result = array(
        'revisionID'  => $revision->getID(),
        'action'      => $action,
        'authorPHID'  => $xaction->getAuthorPHID(),
        'dateCreated' => $xaction->getDateCreated(),
        'content'     => ($xaction->hasComment()
          ? $xaction->getComment()->getContent()
          : null),
      );

      $results[$revision->getID()][] = $result;
    }

    return $results;
  }

}

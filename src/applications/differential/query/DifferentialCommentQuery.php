<?php

/**
 * Temporary wrapper for transitioning Differential to ApplicationTransactions.
 */
final class DifferentialCommentQuery
  extends PhabricatorOffsetPagedQuery {

  private $revisionPHIDs;

  public function withRevisionPHIDs(array $phids) {
    $this->revisionPHIDs = $phids;
    return $this;
  }

  public function execute() {
    // TODO: We're getting rid of this, it is the bads.
    $viewer = PhabricatorUser::getOmnipotentUser();

    $xactions = id(new DifferentialTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs($this->revisionPHIDs)
      ->needComments(true)
      ->execute();

    $results = array();
    foreach ($xactions as $xaction) {
      $results[] = DifferentialComment::newFromModernTransaction($xaction);
    }

    return $results;
  }

}

<?php

final class DifferentialTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new DifferentialTransaction();
  }

  public static function loadUnsubmittedInlineComments(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {

    return id(new DifferentialDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withRevisionPHIDs(array($revision->getPHID()))
      ->withAuthorPHIDs(array($viewer->getPHID()))
      ->withHasTransaction(false)
      ->withIsDeleted(false)
      ->needReplyToComments(true)
      ->execute();
  }

}

<?php

final class DifferentialTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new DifferentialTransaction();
  }

  public static function loadUnsubmittedInlineComments(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {

    $inlines = id(new DifferentialDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withRevisionPHIDs(array($revision->getPHID()))
      ->withAuthorPHIDs(array($viewer->getPHID()))
      ->withHasTransaction(false)
      ->withIsDeleted(false)
      ->needReplyToComments(true)
      ->execute();

    // Don't count empty inlines when considering draft state.
    foreach ($inlines as $key => $inline) {
      if ($inline->isEmptyInlineComment()) {
        unset($inlines[$key]);
      }
    }

    return $inlines;
  }

}

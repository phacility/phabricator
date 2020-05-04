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

    foreach ($inlines as $key => $inline) {
      $inlines[$key] = DifferentialInlineComment::newFromModernComment(
        $inline);
    }

    PhabricatorInlineComment::loadAndAttachVersionedDrafts(
      $viewer,
      $inlines);

    // Don't count void inlines when considering draft state.
    foreach ($inlines as $key => $inline) {
      if ($inline->isVoidComment($viewer)) {
        unset($inlines[$key]);
        continue;
      }

      // For other inlines: if they have a nonempty draft state, set their
      // content to the draft state content. We want to submit the comment
      // as it is currently shown to the user, not as it was stored the last
      // time they clicked "Save".

      $draft_content = $inline->getContentForEdit($viewer);
      if (strlen($draft_content)) {
        $inline->setContent($draft_content);
      }
    }

    $inlines = mpull($inlines, 'getStorageObject');

    return $inlines;
  }

}

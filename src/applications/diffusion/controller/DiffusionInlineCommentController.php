<?php

final class DiffusionInlineCommentController
  extends PhabricatorInlineCommentController {

  protected function newInlineCommentQuery() {
    return new DiffusionDiffInlineCommentQuery();
  }

  protected function newContainerObject() {
    return $this->loadCommit();
  }

  private function getCommitPHID() {
    return $this->getRequest()->getURIData('phid');
  }

  private function loadCommit() {
    $viewer = $this->getViewer();
    $commit_phid = $this->getCommitPHID();

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($commit_phid))
      ->executeOne();
    if (!$commit) {
      throw new Exception(pht('Invalid commit PHID "%s"!', $commit_phid));
    }

    return $commit;
  }

  protected function createComment() {
    $commit = $this->loadCommit();

    // TODO: Write a real PathQuery object?
    $path_id = $this->getChangesetID();
    $path = queryfx_one(
      id(new PhabricatorRepository())->establishConnection('r'),
      'SELECT path FROM %T WHERE id = %d',
      PhabricatorRepository::TABLE_PATH,
      $path_id);
    if (!$path) {
      throw new Exception(pht('Invalid path ID!'));
    }

    return id(new PhabricatorAuditInlineComment())
      ->setCommitPHID($commit->getPHID())
      ->setPathID($path_id);
  }

  protected function loadCommentForDone($id) {
    $viewer = $this->getViewer();

    $inline = $this->loadCommentByID($id);
    if (!$inline) {
      throw new Exception(pht('Failed to load comment "%d".', $id));
    }

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($inline->getCommitPHID()))
      ->executeOne();
    if (!$commit) {
      throw new Exception(pht('Failed to load commit.'));
    }

    $owner_phid = $commit->getAuthorPHID();
    $viewer_phid = $viewer->getPHID();
    $viewer_is_owner = ($owner_phid && ($owner_phid == $viewer_phid));
    $viewer_is_author = ($viewer_phid == $inline->getAuthorPHID());
    $is_draft = $inline->isDraft();

    if ($viewer_is_owner) {
      // You can mark inlines on your own commits as "Done".
    } else if ($viewer_is_author && $is_draft) {
      // You can mark your own unsubmitted inlines as "Done".
    } else {
      throw new Exception(
        pht(
          'You can not mark this comment as complete: you did not author '.
          'the commit and the comment is not a draft you wrote.'));
    }

    return $inline;
  }

  protected function canEditInlineComment(
    PhabricatorUser $viewer,
    PhabricatorAuditInlineComment $inline) {

    // Only the author may edit a comment.
    if ($inline->getAuthorPHID() != $viewer->getPHID()) {
      return false;
    }

    // Saved comments may not be edited.
    if ($inline->getTransactionPHID()) {
      return false;
    }

    // Inline must be attached to the active revision.
    if ($inline->getCommitPHID() != $this->getCommitPHID()) {
      return false;
    }

    return true;
  }

  protected function loadObjectOwnerPHID(
    PhabricatorInlineComment $inline) {
    return $this->loadCommit()->getAuthorPHID();
  }


}

<?php

final class DiffusionInlineCommentController
  extends PhabricatorInlineCommentController {

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
      throw new Exception('Invalid path ID!');
    }

    return id(new PhabricatorAuditInlineComment())
      ->setCommitPHID($commit->getPHID())
      ->setPathID($path_id);
  }

  protected function loadComment($id) {
    return PhabricatorAuditInlineComment::loadID($id);
  }

  protected function loadCommentByPHID($phid) {
    return PhabricatorAuditInlineComment::loadPHID($phid);
  }

  protected function loadCommentForEdit($id) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $inline = $this->loadComment($id);
    if (!$this->canEditInlineComment($user, $inline)) {
      throw new Exception('That comment is not editable!');
    }
    return $inline;
  }

  protected function loadCommentForDone($id) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $inline = $this->loadComment($id);
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

    if ((!$commit->getAuthorPHID()) ||
        ($commit->getAuthorPHID() != $viewer->getPHID())) {
      throw new Exception(pht('You can not mark this comment as complete.'));
    }

    return $inline;
  }

  private function canEditInlineComment(
    PhabricatorUser $user,
    PhabricatorAuditInlineComment $inline) {

    // Only the author may edit a comment.
    if ($inline->getAuthorPHID() != $user->getPHID()) {
      return false;
    }

    // Saved comments may not be edited.
    if ($inline->getAuditCommentID()) {
      return false;
    }

    // Inline must be attached to the active revision.
    if ($inline->getCommitPHID() != $this->getCommitPHID()) {
      return false;
    }

    return true;
  }

  protected function deleteComment(PhabricatorInlineCommentInterface $inline) {
    return $inline->delete();
  }

  protected function saveComment(PhabricatorInlineCommentInterface $inline) {
    return $inline->save();
  }

  protected function loadObjectOwnerPHID(
    PhabricatorInlineCommentInterface $inline) {
    return $this->loadCommit()->getAuthorPHID();
  }


}

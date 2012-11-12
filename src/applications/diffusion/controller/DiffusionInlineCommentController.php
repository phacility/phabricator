<?php

final class DiffusionInlineCommentController
  extends PhabricatorInlineCommentController {

  private $commitPHID;

  public function willProcessRequest(array $data) {
    $this->commitPHID = $data['phid'];
  }

  protected function createComment() {

    // Verify commit and path correspond to actual objects.
    $commit_phid = $this->commitPHID;
    $path_id = $this->getChangesetID();

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'phid = %s',
      $commit_phid);
    if (!$commit) {
      throw new Exception("Invalid commit ID!");
    }

    // TODO: Write a real PathQuery object?

    $path = queryfx_one(
      id(new PhabricatorRepository())->establishConnection('r'),
      'SELECT path FROM %T WHERE id = %d',
      PhabricatorRepository::TABLE_PATH,
      $path_id);

    if (!$path) {
      throw new Exception("Invalid path ID!");
    }

    return id(new PhabricatorAuditInlineComment())
      ->setCommitPHID($commit_phid)
      ->setPathID($path_id);
  }

  protected function loadComment($id) {
    return id(new PhabricatorAuditInlineComment())->load($id);
  }

  protected function loadCommentForEdit($id) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $inline = $this->loadComment($id);
    if (!$this->canEditInlineComment($user, $inline)) {
      throw new Exception("That comment is not editable!");
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
    if ($inline->getCommitPHID() != $this->commitPHID) {
      return false;
    }

    return true;
  }

}

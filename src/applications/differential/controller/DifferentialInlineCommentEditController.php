<?php

final class DifferentialInlineCommentEditController
  extends PhabricatorInlineCommentController {

  private function getRevisionID() {
    return $this->getRequest()->getURIData('id');
  }

  private function loadRevision() {
    $viewer = $this->getViewer();
    $revision_id = $this->getRevisionID();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($revision_id))
      ->executeOne();
   if (!$revision) {
      throw new Exception(pht('Invalid revision ID "%s".', $revision_id));
    }

    return $revision;
  }

  protected function createComment() {
    // Verify revision and changeset correspond to actual objects, and are
    // connected to one another.
    $changeset_id = $this->getChangesetID();
    $viewer = $this->getViewer();

    $revision = $this->loadRevision();

    $changeset = id(new DifferentialChangesetQuery())
      ->setViewer($viewer)
      ->withIDs(array($changeset_id))
      ->executeOne();
    if (!$changeset) {
      throw new Exception(
        pht(
          'Invalid changeset ID "%s"!',
          $changeset_id));
    }

    $diff = $changeset->getDiff();
    if ($diff->getRevisionID() != $revision->getID()) {
      throw new Exception(
        pht(
          'Changeset ID "%s" is part of diff ID "%s", but that diff '.
          'is attached to revision "%s", not revision "%s".',
          $changeset_id,
          $diff->getID(),
          $diff->getRevisionID(),
          $revision->getID()));
    }

    return id(new DifferentialInlineComment())
      ->setRevision($revision)
      ->setChangesetID($changeset_id);
  }

  protected function loadComment($id) {
    return id(new DifferentialInlineCommentQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($id))
      ->withDeletedDrafts(true)
      ->needHidden(true)
      ->executeOne();
  }

  protected function loadCommentByPHID($phid) {
    return id(new DifferentialInlineCommentQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->withDeletedDrafts(true)
      ->needHidden(true)
      ->executeOne();
  }

  protected function loadCommentForEdit($id) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $inline = $this->loadComment($id);
    if (!$this->canEditInlineComment($user, $inline)) {
      throw new Exception(pht('That comment is not editable!'));
    }
    return $inline;
  }

  protected function loadCommentForDone($id) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $inline = $this->loadComment($id);
    if (!$inline) {
      throw new Exception(pht('Unable to load inline "%d".', $id));
    }

    $changeset = id(new DifferentialChangesetQuery())
      ->setViewer($viewer)
      ->withIDs(array($inline->getChangesetID()))
      ->executeOne();
    if (!$changeset) {
      throw new Exception(pht('Unable to load changeset.'));
    }

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($changeset->getDiffID()))
      ->executeOne();
    if (!$diff) {
      throw new Exception(pht('Unable to load diff.'));
    }

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($diff->getRevisionID()))
      ->executeOne();
    if (!$revision) {
      throw new Exception(pht('Unable to load revision.'));
    }

    if ($revision->getAuthorPHID() !== $viewer->getPHID()) {
      throw new Exception(pht('You are not the revision owner.'));
    }

    return $inline;
  }

  private function canEditInlineComment(
    PhabricatorUser $user,
    DifferentialInlineComment $inline) {

    // Only the author may edit a comment.
    if ($inline->getAuthorPHID() != $user->getPHID()) {
      return false;
    }

    // Saved comments may not be edited, for now, although the schema now
    // supports it.
    if (!$inline->isDraft()) {
      return false;
    }

    // Inline must be attached to the active revision.
    if ($inline->getRevisionID() != $this->getRevisionID()) {
      return false;
    }

    return true;
  }

  protected function deleteComment(PhabricatorInlineCommentInterface $inline) {
    $inline->openTransaction();

      $inline->setIsDeleted(1)->save();
      DifferentialDraft::deleteHasDraft(
        $inline->getAuthorPHID(),
        $inline->getRevisionPHID(),
        $inline->getPHID());

    $inline->saveTransaction();
  }

  protected function undeleteComment(
    PhabricatorInlineCommentInterface $inline) {
    $inline->openTransaction();

      $inline->setIsDeleted(0)->save();
      DifferentialDraft::markHasDraft(
        $inline->getAuthorPHID(),
        $inline->getRevisionPHID(),
        $inline->getPHID());

    $inline->saveTransaction();
  }

  protected function saveComment(PhabricatorInlineCommentInterface $inline) {
    $inline->openTransaction();
      $inline->save();
      DifferentialDraft::markHasDraft(
        $inline->getAuthorPHID(),
        $inline->getRevisionPHID(),
        $inline->getPHID());
    $inline->saveTransaction();
  }

  protected function loadObjectOwnerPHID(
    PhabricatorInlineCommentInterface $inline) {
    return $this->loadRevision()->getAuthorPHID();
  }

  protected function hideComments(array $ids) {
    $viewer = $this->getViewer();
    $table = new DifferentialHiddenComment();
    $conn_w = $table->establishConnection('w');

    $sql = array();
    foreach ($ids as $id) {
      $sql[] = qsprintf(
        $conn_w,
        '(%s, %d)',
        $viewer->getPHID(),
        $id);
    }

    queryfx(
      $conn_w,
      'INSERT IGNORE INTO %T (userPHID, commentID) VALUES %Q',
      $table->getTableName(),
      implode(', ', $sql));
  }

  protected function showComments(array $ids) {
    $viewer = $this->getViewer();
    $table = new DifferentialHiddenComment();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE userPHID = %s AND commentID IN (%Ld)',
      $table->getTableName(),
      $viewer->getPHID(),
      $ids);
  }

}

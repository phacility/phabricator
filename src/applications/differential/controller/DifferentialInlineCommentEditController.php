<?php

final class DifferentialInlineCommentEditController
  extends PhabricatorInlineCommentController {

  protected function newInlineCommentQuery() {
    return new DifferentialDiffInlineCommentQuery();
  }

  protected function newContainerObject() {
    return $this->loadRevision();
  }

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

  protected function loadCommentForDone($id) {
    $viewer = $this->getViewer();

    $inline = $this->loadCommentByID($id);
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

    $viewer_phid = $viewer->getPHID();
    $is_owner = ($viewer_phid == $revision->getAuthorPHID());
    $is_author = ($viewer_phid == $inline->getAuthorPHID());
    $is_draft = ($inline->isDraft());

    if ($is_owner) {
      // You own the revision, so you can mark the comment as "Done".
    } else if ($is_author && $is_draft) {
      // You made this comment and it's still a draft, so you can mark
      // it as "Done".
    } else {
      throw new Exception(
        pht(
          'You are not the revision owner, and this is not a draft comment '.
          'you authored.'));
    }

    return $inline;
  }

  protected function canEditInlineComment(
    PhabricatorUser $viewer,
    DifferentialInlineComment $inline) {

    // Only the author may edit a comment.
    if ($inline->getAuthorPHID() != $viewer->getPHID()) {
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

  protected function loadObjectOwnerPHID(
    PhabricatorInlineComment $inline) {
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
      'INSERT IGNORE INTO %T (userPHID, commentID) VALUES %LQ',
      $table->getTableName(),
      $sql);
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

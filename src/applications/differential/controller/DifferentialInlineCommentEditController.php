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
    // Verify revision and changeset correspond to actual objects.
    $changeset_id = $this->getChangesetID();

    $revision = $this->loadRevision();

    if (!id(new DifferentialChangeset())->load($changeset_id)) {
      throw new Exception('Invalid changeset ID!');
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
      ->executeOne();
  }

  protected function loadCommentByPHID($phid) {
    return id(new DifferentialInlineCommentQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->withDeletedDrafts(true)
      ->executeOne();
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
      DifferentialDraft::deleteHasDraft(
        $inline->getAuthorPHID(),
        $inline->getRevisionPHID(),
        $inline->getPHID());
      $inline->delete();
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

}

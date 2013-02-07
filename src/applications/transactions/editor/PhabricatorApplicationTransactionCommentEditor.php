<?php

final class PhabricatorApplicationTransactionCommentEditor
  extends PhabricatorEditor {

  private $contentSource;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  /**
   * Edit a transaction's comment. This method effects the required create,
   * update or delete to set the transaction's comment to the provided comment.
   */
  public function applyEdit(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorApplicationTransactionComment $comment) {

    $this->validateEdit($xaction, $comment);

    $actor = $this->requireActor();

    $comment->setContentSource($this->getContentSource());
    $comment->setAuthorPHID($actor->getPHID());

    // TODO: This needs to be more sophisticated once we have meta-policies.
    $comment->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC);
    $comment->setEditPolicy($actor->getPHID());

    $xaction->openTransaction();
      $xaction->beginReadLocking();
        if ($xaction->getID()) {
          $xaction->reload();
        }

        $new_version = $xaction->getCommentVersion() + 1;

        $comment->setCommentVersion($new_version);
        $comment->setTransactionPHID($xaction->getPHID());
        $comment->save();

        $xaction->setCommentVersion($new_version);
        $xaction->setCommentPHID($comment->getPHID());
        $xaction->setViewPolicy($comment->getViewPolicy());
        $xaction->setEditPolicy($comment->getEditPolicy());
        $xaction->save();

      $xaction->endReadLocking();
    $xaction->saveTransaction();

    $xaction->attachComment($comment);

    // TODO: Emit an event for notifications/feed? Can we handle them
    // generically?

    return $this;
  }

  /**
   * Validate that the edit is permissible, and the actor has permission to
   * perform it.
   */
  private function validateEdit(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorApplicationTransactionComment $comment) {

    if (!$xaction->getPHID()) {
      throw new Exception(
        "Transaction must have a PHID before calling applyEdit()!");
    }

    $type_comment = PhabricatorTransactions::TYPE_COMMENT;
    if ($xaction->getTransactionType() == $type_comment) {
      if ($comment->getPHID()) {
        throw new Exception(
        "Transaction comment must not yet have a PHID!");
      }
    }

    if (!$this->getContentSource()) {
      throw new Exception(
        "Call setContentSource() before applyEdit()!");
    }

    $actor = $this->requireActor();

    PhabricatorPolicyFilter::requireCapability(
      $actor,
      $xaction,
      PhabricatorPolicyCapability::CAN_VIEW);

    PhabricatorPolicyFilter::requireCapability(
      $actor,
      $xaction,
      PhabricatorPolicyCapability::CAN_EDIT);
  }


}

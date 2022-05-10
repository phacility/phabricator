<?php

final class PhabricatorApplicationTransactionCommentEditor
  extends PhabricatorEditor {

  private $contentSource;
  private $actingAsPHID;
  private $request;
  private $cancelURI;
  private $isNewComment;

  public function setActingAsPHID($acting_as_phid) {
    $this->actingAsPHID = $acting_as_phid;
    return $this;
  }

  public function getActingAsPHID() {
    if ($this->actingAsPHID) {
      return $this->actingAsPHID;
    }
    return $this->getActor()->getPHID();
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function getCancelURI() {
    return $this->cancelURI;
  }

  public function setIsNewComment($is_new) {
    $this->isNewComment = $is_new;
    return $this;
  }

  public function getIsNewComment() {
    return $this->isNewComment;
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

    $this->applyMFAChecks($xaction, $comment);

    $comment->setContentSource($this->getContentSource());
    $comment->setAuthorPHID($this->getActingAsPHID());

    // TODO: This needs to be more sophisticated once we have meta-policies.
    $comment->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC);
    $comment->setEditPolicy($this->getActingAsPHID());

    $xaction->openTransaction();
      $xaction->beginReadLocking();
        if ($xaction->getID()) {
          $xaction->reload();
        }

        $new_version = $xaction->getCommentVersion() + 1;

        $comment->setCommentVersion($new_version);
        $comment->setTransactionPHID($xaction->getPHID());
        $comment->save();

        $old_comment = $xaction->getComment();
        $comment->attachOldComment($old_comment);

        $xaction->setCommentVersion($new_version);
        $xaction->setCommentPHID($comment->getPHID());
        $xaction->setViewPolicy($comment->getViewPolicy());
        $xaction->setEditPolicy($comment->getEditPolicy());
        $xaction->save();
        $xaction->attachComment($comment);

        // For comment edits, we need to make sure there are no automagical
        // transactions like adding mentions or projects.
        if ($new_version > 1) {
          $object = id(new PhabricatorObjectQuery())
            ->withPHIDs(array($xaction->getObjectPHID()))
            ->setViewer($this->getActor())
            ->executeOne();
          if ($object &&
              $object instanceof PhabricatorApplicationTransactionInterface) {
            $editor = $object->getApplicationTransactionEditor();
            $editor->setActor($this->getActor());
            $support_xactions = $editor->getExpandedSupportTransactions(
              $object,
              $xaction);
            if ($support_xactions) {
              $editor
                ->setContentSource($this->getContentSource())
                ->setContinueOnNoEffect(true)
                ->setContinueOnMissingFields(true)
                ->applyTransactions($object, $support_xactions);
            }
          }
        }
      $xaction->endReadLocking();
    $xaction->saveTransaction();

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
        pht(
          'Transaction must have a PHID before calling %s!',
          'applyEdit()'));
    }

    $type_comment = PhabricatorTransactions::TYPE_COMMENT;
    if ($xaction->getTransactionType() == $type_comment) {
      if ($comment->getPHID()) {
        throw new Exception(
          pht('Transaction comment must not yet have a PHID!'));
      }
    }

    if (!$this->getContentSource()) {
      throw new PhutilInvalidStateException('applyEdit');
    }

    $actor = $this->requireActor();

    PhabricatorPolicyFilter::requireCapability(
      $actor,
      $xaction,
      PhabricatorPolicyCapability::CAN_VIEW);

    if ($comment->getIsRemoved() && $actor->getIsAdmin()) {
      // NOTE: Administrators can remove comments by any user, and don't need
      // to pass the edit check.
    } else {
      PhabricatorPolicyFilter::requireCapability(
        $actor,
        $xaction,
        PhabricatorPolicyCapability::CAN_EDIT);

      PhabricatorPolicyFilter::requireCanInteract(
        $actor,
        $xaction->getObject());
    }
  }

  private function applyMFAChecks(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorApplicationTransactionComment $comment) {
    $actor = $this->requireActor();

    // We don't do any MFA checks here when you're creating a comment for the
    // first time (the parent editor handles them for us), so we can just bail
    // out if this is the creation flow.
    if ($this->getIsNewComment()) {
      return;
    }

    $request = $this->getRequest();
    if (!$request) {
      throw new PhutilInvalidStateException('setRequest');
    }

    $cancel_uri = $this->getCancelURI();
    if (!strlen($cancel_uri)) {
      throw new PhutilInvalidStateException('setCancelURI');
    }

    // If you're deleting a comment, we try to prompt you for MFA if you have
    // it configured, but do not require that you have it configured. In most
    // cases, this is administrators removing content.

    // See PHI1173. If you're editing a comment you authored and the original
    // comment was signed with MFA, you MUST have MFA on your account and you
    // MUST sign the edit with MFA. Otherwise, we can end up with an MFA badge
    // on different content than what was signed.

    $want_mfa = false;
    $need_mfa = false;

    if ($comment->getIsRemoved()) {
      // Try to prompt on removal.
      $want_mfa = true;
    }

    if ($xaction->getIsMFATransaction()) {
      if ($actor->getPHID() === $xaction->getAuthorPHID()) {
        // Strictly require MFA if the original transaction was signed and
        // you're the author.
        $want_mfa = true;
        $need_mfa = true;
      }
    }

    if (!$want_mfa) {
      return;
    }

    if ($need_mfa) {
      $factors = id(new PhabricatorAuthFactorConfigQuery())
        ->setViewer($actor)
        ->withUserPHIDs(array($this->getActingAsPHID()))
        ->withFactorProviderStatuses(
          array(
            PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE,
            PhabricatorAuthFactorProviderStatus::STATUS_DEPRECATED,
          ))
        ->execute();
      if (!$factors) {
        $error = new PhabricatorApplicationTransactionValidationError(
          $xaction->getTransactionType(),
          pht('No MFA'),
          pht(
            'This comment was signed with MFA, so edits to it must also be '.
            'signed with MFA. You do not have any MFA factors attached to '.
            'your account, so you can not sign this edit. Add MFA to your '.
            'account in Settings.'),
          $xaction);

        throw new PhabricatorApplicationTransactionValidationException(
          array(
            $error,
          ));
      }
    }

    $workflow_key = sprintf(
      'comment.edit(%s, %d)',
      $xaction->getPHID(),
      $xaction->getComment()->getID());

    $hisec_token = id(new PhabricatorAuthSessionEngine())
      ->setWorkflowKey($workflow_key)
      ->requireHighSecurityToken($actor, $request, $cancel_uri);
  }

}

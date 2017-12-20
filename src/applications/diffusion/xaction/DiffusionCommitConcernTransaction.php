<?php

final class DiffusionCommitConcernTransaction
  extends DiffusionCommitAuditTransaction {

  const TRANSACTIONTYPE = 'diffusion.commit.concern';
  const ACTIONKEY = 'concern';

  protected function getCommitActionLabel() {
    return pht("Raise Concern \xE2\x9C\x98");
  }

  protected function getCommitActionDescription() {
    return pht('This commit will be returned to the author for consideration.');
  }

  public function getIcon() {
    return 'fa-times-circle-o';
  }

  public function getColor() {
    return 'red';
  }

  protected function getCommitActionOrder() {
    return 600;
  }

  public function getActionName() {
    return pht('Raised Concern');
  }

  public function applyInternalEffects($object, $value) {
    // NOTE: We force the commit directly into "Concern Raised" so that we
    // override a possible "Needs Verification" state.
    $object->setAuditStatus(
      PhabricatorAuditCommitStatusConstants::CONCERN_RAISED);
  }

  public function applyExternalEffects($object, $value) {
    $status = PhabricatorAuditStatusConstants::CONCERNED;
    $actor = $this->getActor();
    $this->applyAuditorEffect($object, $actor, $value, $status);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($this->isViewerCommitAuthor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not raise a concern with this commit because you are '.
          'the commit author. You can only raise concerns with commits '.
          'you did not author.'));
    }

    // Even if you've already raised a concern, you can raise again as long
    // as the author requested you verify.
    $state_verify = PhabricatorAuditCommitStatusConstants::NEEDS_VERIFICATION;

    if ($this->isViewerFullyRejected($object, $viewer)) {
      if ($object->getAuditStatus() != $state_verify) {
        throw new Exception(
          pht(
            'You can not raise a concern with this commit because you have '.
            'already raised a concern with it.'));
      }
    }
  }

  public function getTitle() {
    return pht(
      '%s raised a concern with this commit.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s raised a concern with %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}

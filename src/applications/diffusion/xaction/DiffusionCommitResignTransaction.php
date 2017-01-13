<?php

final class DiffusionCommitResignTransaction
  extends DiffusionCommitAuditTransaction {

  const TRANSACTIONTYPE = 'diffusion.commit.resign';
  const ACTIONKEY = 'resign';

  protected function getCommitActionLabel() {
    return pht('Resign as Auditor');
  }

  protected function getCommitActionDescription() {
    return pht('You will resign as an auditor for this commit.');
  }

  public function getIcon() {
    return 'fa-flag';
  }

  public function getColor() {
    return 'orange';
  }

  protected function getCommitActionOrder() {
    return 700;
  }

  public function getActionName() {
    return pht('Resigned');
  }

  public function generateOldValue($object) {
    $actor = $this->getActor();
    return !$this->isViewerAnyActiveAuditor($object, $actor);
  }

  public function applyExternalEffects($object, $value) {
    $status = PhabricatorAuditStatusConstants::RESIGNED;
    $actor = $this->getActor();
    $this->applyAuditorEffect($object, $actor, $value, $status);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if (!$this->isViewerAnyActiveAuditor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not resign from this commit because you are not an '.
          'active auditor.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s resigned from this commit.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s resigned from %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}

<?php

final class DiffusionCommitVerifyTransaction
  extends DiffusionCommitAuditTransaction {

  const TRANSACTIONTYPE = 'diffusion.commit.verify';
  const ACTIONKEY = 'verify';

  protected function getCommitActionLabel() {
    return pht('Request Verification');
  }

  protected function getCommitActionDescription() {
    return pht(
      'Auditors will be asked to verify that concerns have been addressed.');
  }

  protected function getCommitActionGroupKey() {
    return DiffusionCommitEditEngine::ACTIONGROUP_COMMIT;
  }

  public function getIcon() {
    return 'fa-refresh';
  }

  public function getColor() {
    return 'indigo';
  }

  protected function getCommitActionOrder() {
    return 600;
  }

  public function getActionName() {
    return pht('Requested Verification');
  }

  public function applyInternalEffects($object, $value) {
    $object->setAuditStatus(DiffusionCommitAuditStatus::NEEDS_VERIFICATION);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if (!$this->isViewerCommitAuthor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not request verification of this commit because you '.
          'are not the author.'));
    }

    if (!$object->isAuditStatusConcernRaised()) {
      throw new Exception(
        pht(
          'You can not request verification of this commit because no '.
          'auditors have raised concerns with it.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s requested verification of this commit.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s requested verification of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'request-verification';
  }

  public function getFieldValuesForConduit($object, $data) {
    return array();
  }

}

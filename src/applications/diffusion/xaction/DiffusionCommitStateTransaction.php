<?php

final class DiffusionCommitStateTransaction
  extends DiffusionCommitTransactionType {

  const TRANSACTIONTYPE = 'diffusion.commit.state';

  public function generateNewValue($object, $value) {
    // NOTE: This transaction can not be generated or applied normally. It is
    // written to the transaction log as a side effect of a state change.
    throw new PhutilMethodNotImplementedException();
  }

  public function getIcon() {
    $new = $this->getNewValue();
    return PhabricatorAuditCommitStatusConstants::getStatusIcon($new);
  }

  public function getColor() {
    $new = $this->getNewValue();
    return PhabricatorAuditCommitStatusConstants::getStatusColor($new);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    switch ($new) {
      case PhabricatorAuditCommitStatusConstants::NONE:
        return pht('This commit no longer requires audit.');
      case PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT:
        return pht('This commit now requires audit.');
      case PhabricatorAuditCommitStatusConstants::CONCERN_RAISED:
        return pht('This commit now has outstanding concerns.');
      case PhabricatorAuditCommitStatusConstants::NEEDS_VERIFICATION:
        return pht('This commit now requires verification by auditors.');
      case PhabricatorAuditCommitStatusConstants::FULLY_AUDITED:
        return pht('All concerns with this commit have now been addressed.');
    }

    return null;
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    switch ($new) {
      case PhabricatorAuditCommitStatusConstants::NONE:
        return pht(
          '%s no longer requires audit.',
          $this->renderObject());
      case PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT:
        return pht(
          '%s now requires audit.',
          $this->renderObject());
      case PhabricatorAuditCommitStatusConstants::CONCERN_RAISED:
        return pht(
          '%s now has outstanding concerns.',
          $this->renderObject());
      case PhabricatorAuditCommitStatusConstants::NEEDS_VERIFICATION:
        return pht(
          '%s now requires verification by auditors.',
          $this->renderObject());
      case PhabricatorAuditCommitStatusConstants::FULLY_AUDITED:
        return pht(
          'All concerns with %s have now been addressed.',
          $this->renderObject());
    }

    return null;
  }

}

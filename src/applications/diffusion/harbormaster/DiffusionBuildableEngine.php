<?php

final class DiffusionBuildableEngine
  extends HarbormasterBuildableEngine {

  public function publishBuildable(
    HarbormasterBuildable $old,
    HarbormasterBuildable $new) {

    // Don't publish manual buildables.
    if ($new->getIsManualBuildable()) {
      return;
    }

    // Don't publish anything if the buildable status has not changed. At
    // least for now, Diffusion handles buildable status exactly the same
    // way that Harbormaster does.
    $old_status = $old->getBuildableStatus();
    $new_status = $new->getBuildableStatus();
    if ($old_status === $new_status) {
      return;
    }

    // Don't publish anything if the buildable is still building.
    if ($new->isBuilding()) {
      return;
    }

    $xaction = $this->newTransaction()
      ->setMetadataValue('harbormaster:buildablePHID', $new->getPHID())
      ->setTransactionType(DiffusionCommitBuildableTransaction::TRANSACTIONTYPE)
      ->setNewValue($new->getBuildableStatus());

    $this->applyTransactions(array($xaction));
  }

  public function getAuthorIdentity() {
    return $this->getObject()
      ->loadIdentities($this->getViewer())
      ->getAuthorIdentity();
  }

}

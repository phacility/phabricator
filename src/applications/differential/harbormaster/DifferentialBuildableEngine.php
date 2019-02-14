<?php

final class DifferentialBuildableEngine
  extends HarbormasterBuildableEngine {

  protected function getPublishableObject() {
    $object = $this->getObject();

    if ($object instanceof DifferentialDiff) {
      if ($object->getRevisionID()) {
        return $object->getRevision();
      } else {
        return null;
      }
    }

    return $object;
  }

  public function publishBuildable(
    HarbormasterBuildable $old,
    HarbormasterBuildable $new) {

    // If we're publishing to a diff that is not actually attached to a
    // revision, we have nothing to publish to, so just bail out.
    $revision = $this->getPublishableObject();
    if (!$revision) {
      return;
    }

    // Don't publish manual buildables.
    if ($new->getIsManualBuildable()) {
      return;
    }

    // Don't publish anything if the buildable is still building. Differential
    // treats more buildables as "building" than Harbormaster does, but the
    // Differential definition is a superset of the Harbormaster definition.
    if ($new->isBuilding()) {
      return;
    }

    $viewer = $this->getViewer();

    $old_status = $revision->getBuildableStatus($new->getPHID());
    $new_status = $revision->newBuildableStatus($viewer, $new->getPHID());
    if ($old_status === $new_status) {
      return;
    }

    $buildable_type = DifferentialRevisionBuildableTransaction::TRANSACTIONTYPE;

    $xaction = $this->newTransaction()
      ->setMetadataValue('harbormaster:buildablePHID', $new->getPHID())
      ->setTransactionType($buildable_type)
      ->setNewValue($new_status);

    $this->applyTransactions(array($xaction));
  }

  public function getAuthorIdentity() {
    $object = $this->getObject();

    if ($object instanceof DifferentialRevision) {
      $object = $object->loadActiveDiff();
    }

    $authorship = $object->getDiffAuthorshipDict();
    if (!isset($authorship['authorName'])) {
      return null;
    }

    $name = $authorship['authorName'];
    $address = idx($authorship, 'authorEmail');

    $full = id(new PhutilEmailAddress())
      ->setDisplayName($name)
      ->setAddress($address);

    return id(new PhabricatorRepositoryIdentity())
      ->setIdentityName((string)$full)
      ->makeEphemeral();
  }

}

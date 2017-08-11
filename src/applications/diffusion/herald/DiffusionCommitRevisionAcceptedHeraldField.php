<?php

final class DiffusionCommitRevisionAcceptedHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.revision.accepted';

  public function getHeraldFieldName() {
    return pht('Accepted Differential revision');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->loadDifferentialRevision();
    if (!$revision) {
      return null;
    }

    if ($revision->isAccepted()) {
      return $revision->getPHID();
    }

    $was_accepted = DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED;
    if ($revision->isPublished()) {
      if ($revision->getProperty($was_accepted)) {
        return $revision->getPHID();
      }
    }

    return null;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_BOOL;
  }

}

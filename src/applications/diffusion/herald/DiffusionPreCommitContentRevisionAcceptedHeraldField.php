<?php

final class DiffusionPreCommitContentRevisionAcceptedHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.revision.accepted';

  public function getHeraldFieldName() {
    return pht('Accepted Differential revision');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->getRevision();

    if (!$revision) {
      return null;
    }

    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
    if ($revision->getStatus() != $status_accepted) {
      return null;
    }

    return $revision->getPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_BOOL;
  }

}

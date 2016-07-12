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

    switch ($revision->getStatus()) {
      case ArcanistDifferentialRevisionStatus::ACCEPTED:
        return $revision->getPHID();
      case ArcanistDifferentialRevisionStatus::CLOSED:
        if ($revision->getProperty(
          DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED)) {

          return $revision->getPHID();
        }
        break;
    }

    return null;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_BOOL;
  }

}

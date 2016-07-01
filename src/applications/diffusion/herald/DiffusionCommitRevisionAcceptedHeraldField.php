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

    $status = $revision->getStatus();

    switch ($status) {
      case ArcanistDifferentialRevisionStatus::ACCEPTED:
        return $revision->getPHID();
      case ArcanistDifferentialRevisionStatus::CLOSED:
        if ($revision->hasRevisionProperty(
            DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED)) {

          if ($revision->getProperty(
              DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED)) {
            return $revision->getPHID();
          } else {
            return null;
          }
        } else {
          // continue on to old-style precommitRevisionStatus
          break;
        }
      default:
        return null;
    }

    $data = $object->getCommitData();
    $status = $data->getCommitDetail('precommitRevisionStatus');

    switch ($status) {
      case ArcanistDifferentialRevisionStatus::ACCEPTED:
      case ArcanistDifferentialRevisionStatus::CLOSED:
        return $revision->getPHID();
    }

    return null;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_BOOL;
  }

}

<?php

final class DiffusionCommitRevisionSubscribersHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.revision.subscribers';

  public function getHeraldFieldName() {
    return pht('Differential subscribers');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->loadDifferentialRevision();

    if (!$revision) {
      return array();
    }

    $phid = $revision->getPHID();
    return PhabricatorSubscribersQuery::loadSubscribersForPHID($phid);
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectOrUserDatasource();
  }

}

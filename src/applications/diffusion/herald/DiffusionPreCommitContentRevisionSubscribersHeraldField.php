<?php

final class DiffusionPreCommitContentRevisionSubscribersHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.revision.subscribers';

  public function getHeraldFieldName() {
    return pht('Differential subscribers');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->getRevision();

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

<?php

final class DiffusionPreCommitContentRevisionSubscribersHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.revision.subscribers';

  public function getHeraldFieldName() {
    return pht('Differential subscribers');
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->getRevision();

    if (!$revision) {
      return array();
    }

    $phid = $revision->getPHID();
    return PhabricatorSubscribersQuery::loadSubscribersForPHID($phid);
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_USER_OR_PROJECT;
    }
  }

}

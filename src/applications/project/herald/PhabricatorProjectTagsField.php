<?php

abstract class PhabricatorProjectTagsField
  extends HeraldField {

  public function getFieldGroupKey() {
    return HeraldSupportFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectDatasource();
  }

  final protected function getProjectTagsTransaction() {
    return $this->getAppliedEdgeTransactionOfType(
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
  }

}

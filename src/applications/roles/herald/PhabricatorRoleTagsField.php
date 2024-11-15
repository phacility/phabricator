<?php

abstract class PhabricatorRoleTagsField
  extends HeraldField {

  public function getFieldGroupKey() {
    return HeraldSupportFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorRoleInterface);
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorRoleDatasource();
  }

  final protected function getRoleTagsTransaction() {
    return $this->getAppliedEdgeTransactionOfType(
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST);
  }

}

<?php

final class HeraldExactProjectsField extends HeraldField {

  const FIELDCONST = 'projects.exact';

  public function getHeraldFieldName() {
    return pht('Projects being edited');
  }

  public function getFieldGroupKey() {
    return PhabricatorProjectHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorProject);
  }

  public function getHeraldFieldValue($object) {
    return array($object->getPHID());
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectDatasource();
  }

}

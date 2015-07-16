<?php

final class DiffusionPreCommitRefChangeHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.change';

  public function getHeraldFieldName() {
    return pht('Ref change type');
  }

  public function getHeraldFieldValue($object) {
    return $object->getChangeFlags();
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_HAS_BIT,
      HeraldAdapter::CONDITION_NOT_BIT,
    );
  }

  public function getHeraldFieldValueType($condition) {
    return id(new HeraldSelectFieldValue())
      ->setKey(self::FIELDCONST)
      ->setOptions(
        PhabricatorRepositoryPushLog::getHeraldChangeFlagConditionOptions())
      ->setDefault(PhabricatorRepositoryPushLog::CHANGEFLAG_ADD);
  }

}

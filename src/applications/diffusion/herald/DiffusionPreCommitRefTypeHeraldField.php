<?php

final class DiffusionPreCommitRefTypeHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.type';

  public function getHeraldFieldName() {
    return pht('Ref type');
  }

  public function getHeraldFieldValue($object) {
    return $object->getRefType();
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_IS,
      HeraldAdapter::CONDITION_IS_NOT,
    );
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldPreCommitRefAdapter::VALUE_REF_TYPE;
  }

}

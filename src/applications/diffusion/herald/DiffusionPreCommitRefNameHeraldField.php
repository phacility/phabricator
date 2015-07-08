<?php

final class DiffusionPreCommitRefNameHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.name';

  public function getHeraldFieldName() {
    return pht('Ref name');
  }

  public function getHeraldFieldValue($object) {
    return $object->getRefName();
  }

  protected function getHeraldFieldStandardConditions() {
    return HeraldField::STANDARD_TEXT;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}

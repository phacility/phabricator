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

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_TEXT;
  }

}

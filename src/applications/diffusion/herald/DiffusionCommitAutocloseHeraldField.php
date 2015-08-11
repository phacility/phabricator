<?php

final class DiffusionCommitAutocloseHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.autoclose';

  public function getHeraldFieldName() {
    return pht('Commit is on autoclose branch');
  }

  public function getHeraldFieldValue($object) {
    return $object->getRepository()->shouldAutocloseCommit($object);
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_UNCONDITIONALLY,
    );
  }

  public function getHeraldFieldValueType($condition) {
    return new HeraldEmptyFieldValue();
  }

}

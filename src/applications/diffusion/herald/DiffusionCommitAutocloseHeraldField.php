<?php

final class DiffusionCommitAutocloseHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.autoclose';

  public function getFieldGroupKey() {
    return HeraldDeprecatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldName() {
    // Herald no longer triggers until a commit is reachable from a permanent
    // ref, so this condition is always true by definition.
    return pht('Commit Autocloses (Deprecated)');
  }

  public function getHeraldFieldValue($object) {
    return true;
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

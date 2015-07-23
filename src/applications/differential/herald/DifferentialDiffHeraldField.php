<?php

abstract class DifferentialDiffHeraldField extends HeraldField {

  public function supportsObject($object) {
    return ($object instanceof DifferentialDiff);
  }

  public function getFieldGroupKey() {
    return DifferentialDiffHeraldFieldGroup::FIELDGROUPKEY;
  }

}

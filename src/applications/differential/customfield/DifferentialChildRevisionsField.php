<?php

final class DifferentialChildRevisionsField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:dependencies';
  }

  public function getFieldName() {
    return pht('Child Revisions');
  }

  public function canDisableField() {
    return false;
  }

  public function getFieldDescription() {
    return pht('Lists revisions this one is depended on by.');
  }

}

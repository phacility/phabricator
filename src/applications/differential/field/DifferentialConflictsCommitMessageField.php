<?php

final class DifferentialConflictsCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'conflicts';

  public function getFieldName() {
    return pht('Conflicts');
  }

  public function getFieldOrder() {
    return 900000;
  }

  public function isFieldEditable() {
    return false;
  }

  public function isTemplateField() {
    return false;
  }

}

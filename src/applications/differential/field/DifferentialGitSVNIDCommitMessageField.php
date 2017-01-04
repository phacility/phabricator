<?php

final class DifferentialGitSVNIDCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'gitSVNID';

  public function getFieldName() {
    return pht('git-svn-id');
  }

  public function getFieldOrder() {
    return 900001;
  }

  public function isFieldEditable() {
    return false;
  }

  public function isTemplateField() {
    return false;
  }

}

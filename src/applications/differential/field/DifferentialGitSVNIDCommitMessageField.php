<?php

final class DifferentialGitSVNIDCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'gitSVNID';

  public function getFieldName() {
    return pht('git-svn-id');
  }

}

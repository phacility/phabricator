<?php

final class DifferentialConflictsCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'conflicts';

  public function getFieldName() {
    return pht('Conflicts');
  }

}

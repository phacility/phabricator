<?php

final class DifferentialSummaryCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'summary';

  public function getFieldName() {
    return pht('Summary');
  }

}

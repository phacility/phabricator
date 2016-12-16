<?php

final class DifferentialSummaryCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'summary';

  public function getFieldName() {
    return pht('Summary');
  }

  public function getFieldOrder() {
    return 2000;
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    return $revision->getSummary();
  }

  public function getFieldTransactions($value) {
    return array(
      array(
        'type' => DifferentialRevisionSummaryTransaction::EDITKEY,
        'value' => $value,
      ),
    );
  }

}

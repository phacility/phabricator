<?php

final class DifferentialJIRAIssuesCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'jira.issues';

  public function getFieldName() {
    return pht('JIRA Issues');
  }

  public function getFieldAliases() {
    return array(
      'JIRA',
      'JIRA Issue',
    );
  }

  public function parseFieldValue($value) {
    return preg_split('/[\s,]+/', $value, $limit = -1, PREG_SPLIT_NO_EMPTY);
  }

  public function isFieldEnabled() {
    return (bool)PhabricatorJIRAAuthProvider::getJIRAProvider();
  }

}

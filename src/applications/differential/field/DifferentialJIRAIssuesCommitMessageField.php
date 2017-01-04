<?php

final class DifferentialJIRAIssuesCommitMessageField
  extends DifferentialCommitMessageCustomField {

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

  public function getCustomFieldKey() {
    return 'phabricator:jira-issues';
  }

  public function parseFieldValue($value) {
    return preg_split('/[\s,]+/', $value, $limit = -1, PREG_SPLIT_NO_EMPTY);
  }

  protected function readFieldValueFromCustomFieldStorage($value) {
    return $this->readJSONFieldValueFromCustomFieldStorage($value, array());
  }

  public function readFieldValueFromConduit($value) {
    return $this->readStringListFieldValueFromConduit($value);
  }

  public function renderFieldValue($value) {
    if (!$value) {
      return null;
    }

    return implode(', ', $value);
  }

}

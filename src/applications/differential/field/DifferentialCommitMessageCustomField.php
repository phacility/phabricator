<?php

abstract class DifferentialCommitMessageCustomField
  extends DifferentialCommitMessageField {

  abstract public function getCustomFieldKey();

  public function getFieldOrder() {
    $custom_key = $this->getCustomFieldKey();
    return 100000 + $this->getCustomFieldOrder($custom_key);
  }

  public function isFieldEnabled() {
    $custom_key = $this->getCustomFieldKey();
    return $this->isCustomFieldEnabled($custom_key);
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    $custom_key = $this->getCustomFieldKey();
    $value = $this->readCustomFieldValue($revision, $custom_key);
    return $value;
  }

  protected function readFieldValueFromCustomFieldStorage($value) {
    return $value;
  }

  protected function readJSONFieldValueFromCustomFieldStorage(
    $value,
    $default) {
    try {
      return phutil_json_decode($value);
    } catch (PhutilJSONParserException $ex) {
      return $default;
    }
  }

  protected function readCustomFieldValue(
    DifferentialRevision $revision,
    $key) {
    $value = idx($this->getCustomFieldStorage(), $key);
    return $this->readFieldValueFromCustomFieldStorage($value);
  }

  protected function getCustomFieldOrder($key) {
    $field_list = PhabricatorCustomField::getObjectFields(
      new DifferentialRevision(),
      PhabricatorCustomField::ROLE_DEFAULT);

    $fields = $field_list->getFields();

    $idx = 0;
    foreach ($fields as $field_key => $value) {
      if ($key === $field_key) {
        return $idx;
      }
      $idx++;
    }

    return $idx;
  }

  public function getFieldTransactions($value) {
    return array(
      array(
        'type' => $this->getCommitMessageFieldKey(),
        'value' => $value,
      ),
    );
  }

}

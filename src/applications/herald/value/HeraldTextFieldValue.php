<?php

final class HeraldTextFieldValue
  extends HeraldFieldValue {

  public function getFieldValueKey() {
    return 'text';
  }

  public function getControlType() {
    return self::CONTROL_TEXT;
  }

  public function renderFieldValue($value) {
    return $value;
  }

  public function renderEditorValue($value) {
    return $value;
  }

}

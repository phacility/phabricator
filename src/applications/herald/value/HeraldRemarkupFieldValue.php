<?php

final class HeraldRemarkupFieldValue
  extends HeraldFieldValue {

  public function getFieldValueKey() {
    return 'remarkup';
  }

  public function getControlType() {
    return self::CONTROL_REMARKUP;
  }

  public function renderFieldValue($value) {
    return $value;
  }

  public function renderEditorValue($value) {
    return $value;
  }

}

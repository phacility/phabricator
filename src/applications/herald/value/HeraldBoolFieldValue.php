<?php

final class HeraldBoolFieldValue
  extends HeraldFieldValue {

  public function getFieldValueKey() {
    return 'bool';
  }

  public function getControlType() {
    return self::CONTROL_NONE;
  }

  public function renderFieldValue($value) {
    return null;
  }

  public function renderEditorValue($value) {
    return null;
  }

  public function renderTranscriptValue($value) {
    if ($value) {
      return pht('true');
    } else {
      return pht('false');
    }
  }

}

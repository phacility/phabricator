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

  public function renderTranscriptValue($value) {
    if (is_array($value)) {
      $value = implode('', $value);
    }

    if (!strlen($value)) {
      return phutil_tag('em', array(), pht('None'));
    }

    if (strlen($value) > 256) {
      $value = phutil_tag(
        'textarea',
        array(
          'class' => 'herald-field-value-transcript',
        ),
        $value);
    }

    return $value;
  }

}

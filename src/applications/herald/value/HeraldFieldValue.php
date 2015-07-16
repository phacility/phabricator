<?php

abstract class HeraldFieldValue extends Phobject {

  const CONTROL_NONE = 'herald.control.none';
  const CONTROL_TEXT = 'herald.control.text';
  const CONTROL_SELECT = 'herald.control.select';
  const CONTROL_TOKENIZER = 'herald.control.tokenizer';

  abstract public function getFieldValueKey();
  abstract public function getControlType();

  final public function getControlSpecificationDictionary() {
    return array(
      'control' => $this->getControlType(),
      'template' => $this->getControlTemplate(),
    );
  }

  protected function getControlTemplate() {
    return array();
  }

}

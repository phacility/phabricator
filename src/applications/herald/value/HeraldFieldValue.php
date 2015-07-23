<?php

abstract class HeraldFieldValue extends Phobject {

  private $viewer;

  const CONTROL_NONE = 'herald.control.none';
  const CONTROL_TEXT = 'herald.control.text';
  const CONTROL_SELECT = 'herald.control.select';
  const CONTROL_TOKENIZER = 'herald.control.tokenizer';

  abstract public function getFieldValueKey();
  abstract public function getControlType();
  abstract public function renderFieldValue($value);
  abstract public function renderEditorValue($value);

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  final public function getControlSpecificationDictionary() {
    return array(
      'key' => $this->getFieldValueKey(),
      'control' => $this->getControlType(),
      'template' => $this->getControlTemplate(),
    );
  }

  protected function getControlTemplate() {
    return array();
  }

}

<?php

/**
 * @group maniphest
 */
class ManiphestAuxiliaryFieldDefaultSpecification
  extends ManiphestAuxiliaryFieldSpecification {

  private $required;
  private $fieldType;

  private $selectOptions;
  private $checkboxLabel;
  private $checkboxValue;
  private $error;
  private $shouldCopyWhenCreatingSimilarTask;

  const TYPE_SELECT = 'select';
  const TYPE_STRING = 'string';
  const TYPE_INT    = 'int';
  const TYPE_BOOL   = 'bool';
  const TYPE_DATE   = 'date';

  public function getFieldType() {
    return $this->fieldType;
  }

  public function setFieldType($val) {
    $this->fieldType = $val;
    return $this;
  }

  public function getError() {
    return $this->error;
  }

  public function setError($val) {
    $this->error = $val;
    return $this;
  }

  public function getSelectOptions() {
    return $this->selectOptions;
  }

  public function setSelectOptions($array) {
    $this->selectOptions = $array;
    return $this;
  }

  public function setRequired($bool) {
    $this->required = $bool;
    return $this;
  }

  public function isRequired() {
    return $this->required;
  }

  public function setCheckboxLabel($checkbox_label) {
    $this->checkboxLabel = $checkbox_label;
    return $this;
  }

  public function getCheckboxLabel() {
    return $this->checkboxLabel;
  }

  public function setCheckboxValue($checkbox_value) {
    $this->checkboxValue = $checkbox_value;
    return $this;
  }

  public function getCheckboxValue() {
    return $this->checkboxValue;
  }

  public function renderControl() {
    $control = null;

    $type = $this->getFieldType();
    switch ($type) {
      case self::TYPE_INT:
        $control = new AphrontFormTextControl();
        break;
      case self::TYPE_STRING:
        $control = new AphrontFormTextControl();
        break;
      case self::TYPE_SELECT:
        $control = new AphrontFormSelectControl();
        $control->setOptions($this->getSelectOptions());
        break;
      case self::TYPE_BOOL:
        $control = new AphrontFormCheckboxControl();
        break;
      case self::TYPE_DATE:
        $control = new AphrontFormDateControl();
        $control->setUser($this->getUser());
        $control->setValue(time());
        break;
      default:
        $label = $this->getLabel();
        throw new ManiphestAuxiliaryFieldTypeException(
          "Field type '{$type}' is not a valid type (for field '{$label}').");
        break;
    }

    switch ($type) {
      case self::TYPE_BOOL:
        $control->addCheckbox(
          'auxiliary['.$this->getAuxiliaryKey().']',
          1,
          $this->getCheckboxLabel(),
          (bool)$this->getValue());
        break;
      case self::TYPE_DATE:
        if ($this->getValue()) {
          $control->setValue($this->getValue());
        }
        $control->setName('auxiliary_date_'.$this->getAuxiliaryKey());
        break;
      default:
        $control->setValue($this->getValue());
        $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
        break;
    }

    $control->setLabel($this->getLabel());
    $control->setCaption($this->getCaption());
    $control->setError($this->getError());

    return $control;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    switch ($this->getFieldType()) {
      case self::TYPE_DATE:
        $control = $this->renderControl();
        $value = $control->readValueFromRequest($request);
        break;
      default:
        $aux_post_values = $request->getArr('auxiliary');
        $value = idx($aux_post_values, $this->getAuxiliaryKey(), '');
        break;
    }
    return $this->setValue($value);
  }

  public function getValueForStorage() {
    return $this->getValue();
  }

  public function setValueFromStorage($value) {
    return $this->setValue($value);
  }

  public function validate() {
    switch ($this->getFieldType()) {
      case self::TYPE_INT:
        if (!is_numeric($this->getValue())) {
          throw new ManiphestAuxiliaryFieldValidationException(
            pht(
              '%s must be an integer value.',
              $this->getLabel()));
        }
        break;
      case self::TYPE_BOOL:
        return true;
      case self::TYPE_STRING:
        return true;
      case self::TYPE_SELECT:
        return true;
      case self::TYPE_DATE:
        if ($this->getValue() <= 0) {
          throw new ManiphestAuxiliaryFieldValidationException(
            pht(
              '%s must be a valid date.',
              $this->getLabel()));
        }
        break;
    }
  }

  public function renderForDetailView() {
    switch ($this->getFieldType()) {
      case self::TYPE_BOOL:
        if ($this->getValue()) {
          return $this->getCheckboxValue();
        } else {
          return null;
        }
      case self::TYPE_SELECT:
        $display = idx($this->getSelectOptions(), $this->getValue());
        return $display;
      case self::TYPE_DATE:
        $display = phabricator_datetime($this->getValue(), $this->getUser());
        return $display;
    }
    return parent::renderForDetailView();
  }

  public function renderTransactionDescription(
    ManiphestTransaction $transaction,
    $target) {

    $label = $this->getLabel();
    $old = $transaction->getOldValue();
    $new = $transaction->getNewValue();

    switch ($this->getFieldType()) {
      case self::TYPE_BOOL:
        if ($new) {
          $desc = "set field '{$label}' true";
        } else {
          $desc = "set field '{$label}' false";
        }
        break;
      case self::TYPE_SELECT:
        $old_display = idx($this->getSelectOptions(), $old);
        $new_display = idx($this->getSelectOptions(), $new);
        if ($old === null) {
          $desc = "set field '{$label}' to '{$new_display}'";
        } else {
          $desc = "changed field '{$label}' ".
                  "from '{$old_display}' to '{$new_display}'";
        }
        break;
      case self::TYPE_DATE:
        $new_display = phabricator_datetime($new, $this->getUser());
        if ($old === null) {
          $desc = "set field '{$label}' to '{$new_display}'";
        } else {
          $old_display = phabricator_datetime($old, $this->getUser());
          $desc = "changed field '{$label}' ".
                  "from '{$old_display}' to '{$new_display}'";
        }
        break;
      default:
        if (!strlen($old)) {
          if (!strlen($new)) {
            return null;
          }
          $desc = "set field '{$label}' to '{$new}'";
        } else {
          $desc = "updated '{$label}' ".
                  "from '{$old}' to '{$new}'";
        }
        break;
    }

    return $desc;
  }

  public function setShouldCopyWhenCreatingSimilarTask($copy) {
    $this->shouldCopyWhenCreatingSimilarTask = $copy;
    return $this;
  }

  public function shouldCopyWhenCreatingSimilarTask() {
    return $this->shouldCopyWhenCreatingSimilarTask;
  }

}

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

  const TYPE_SELECT   = 'select';
  const TYPE_STRING   = 'string';
  const TYPE_INT      = 'int';
  const TYPE_BOOL     = 'bool';
  const TYPE_DATE     = 'date';
  const TYPE_REMARKUP = 'remarkup';
  const TYPE_USER     = 'user';
  const TYPE_USERS    = 'users';

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
        break;
      case self::TYPE_REMARKUP:
        $control = new PhabricatorRemarkupControl();
        $control->setUser($this->getUser());
        break;
      case self::TYPE_USER:
      case self::TYPE_USERS:
        $control = new AphrontFormTokenizerControl();
        $control->setDatasource('/typeahead/common/users/');
        if ($type == self::TYPE_USER) {
          $control->setLimit(1);
        }
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
        $control->setValue($this->getValue());
        $control->setName('auxiliary_date_'.$this->getAuxiliaryKey());
        break;
      case self::TYPE_USER:
      case self::TYPE_USERS:
        $control->setName('auxiliary_tokenizer_'.$this->getAuxiliaryKey());
        $value = array();
        foreach ($this->getValue() as $phid) {
          $value[$phid] = $this->getHandle($phid)->getFullName();
        }
        $control->setValue($value);
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
    $type = $this->getFieldType();
    switch ($type) {
      case self::TYPE_DATE:
        $control = $this->renderControl();
        $value = $control->readValueFromRequest($request);
        break;
      case self::TYPE_USER:
      case self::TYPE_USERS:
        $name = 'auxiliary_tokenizer_'.$this->getAuxiliaryKey();
        $value = $request->getArr($name);
        if ($type == self::TYPE_USER) {
          $value = array_slice($value, 0, 1);
        }
        break;
      default:
        $aux_post_values = $request->getArr('auxiliary');
        $value = idx($aux_post_values, $this->getAuxiliaryKey(), '');
        break;
    }
    return $this->setValue($value);
  }

  public function getValueForStorage() {
    switch ($this->getFieldType()) {
      case self::TYPE_USER:
      case self::TYPE_USERS:
        return json_encode($this->getValue());
      default:
        return $this->getValue();
    }
  }

  public function setValueFromStorage($value) {
    switch ($this->getFieldType()) {
      case self::TYPE_USER:
      case self::TYPE_USERS:
        $value = json_decode($value, true);
        if (!is_array($value)) {
          $value = array();
        }
        break;
      case self::TYPE_DATE:
        $value = (int)$value;
        if ($value <= 0) {
          return $this->setDefaultValue($value);
        }
        break;
      default:
        break;
    }
    return $this->setValue($value);
  }

  public function validate() {
    switch ($this->getFieldType()) {
      case self::TYPE_INT:
        if ($this->getValue() && !is_numeric($this->getValue())) {
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
        if ((int)$this->getValue() <= 0) {
          throw new ManiphestAuxiliaryFieldValidationException(
            pht(
              '%s must be a valid date.',
              $this->getLabel()));
        }
        break;
      case self::TYPE_USER:
      case self::TYPE_USERS:
        if (!is_array($this->getValue())) {
          throw new ManiphestAuxiliaryFieldValidationException(
            pht(
              '%s is not a valid list of user PHIDs.',
              $this->getLabel()));
        }
        break;
    }
  }

  public function setDefaultValue($value) {
    switch ($this->getFieldType()) {
      case self::TYPE_DATE:
        $value = strtotime($value);
        if ($value <= 0) {
          $value = time();
        }
        $this->setValue($value);
        break;
      case self::TYPE_USER:
      case self::TYPE_USERS:
        if (!is_array($value)) {
          $value = array();
        } else {
          $value = array_values($value);
        }
        $this->setValue($value);
        break;
      default:
        $this->setValue((string)$value);
        break;
    }
  }

  public function getMarkupFields() {
    switch ($this->getFieldType()) {
      case self::TYPE_REMARKUP:
        return array('default');
    }
    return parent::getMarkupFields();
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
        return idx($this->getSelectOptions(), $this->getValue());
      case self::TYPE_DATE:
        return phabricator_datetime($this->getValue(), $this->getUser());
      case self::TYPE_REMARKUP:
        return $this->getMarkupEngine()->getOutput(
          $this,
          'default');
      case self::TYPE_USER:
      case self::TYPE_USERS:
        return $this->renderHandleList($this->getValue());
    }
    return parent::renderForDetailView();
  }

  public function getRequiredHandlePHIDs() {
    switch ($this->getFieldType()) {
      case self::TYPE_USER;
      case self::TYPE_USERS:
        return $this->getValue();
    }
    return parent::getRequiredHandlePHIDs();
  }

  protected function renderHandleList(array $phids) {
    $links = array();
    foreach ($phids as $phid) {
      $links[] = $this->getHandle($phid)->renderLink();
    }
    return phutil_implode_html(', ', $links);
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
        // NOTE: Although it should be impossible to get bad data in these
        // fields normally, users can change the type of an existing field and
        // leave us with uninterpretable data in old transactions.
        if ((int)$new <= 0) {
          $new_display = "(invalid epoch timestamp: {$new})";
        } else {
          $new_display = phabricator_datetime($new, $this->getUser());
        }
        if ($old === null) {
          $desc = "set field '{$label}' to '{$new_display}'";
        } else {
          if ((int)$old <= 0) {
            $old_display = "(invalid epoch timestamp: {$old})";
          } else {
            $old_display = phabricator_datetime($old, $this->getUser());
          }
          $desc = "changed field '{$label}' ".
                  "from '{$old_display}' to '{$new_display}'";
        }
        break;
      case self::TYPE_REMARKUP:
        // TODO: After we get ApplicationTransactions, straighten this out.
        $desc = "updated field '{$label}'";
        break;
      case self::TYPE_USER:
      case self::TYPE_USERS:
        // TODO: As above, this is a mess that should get straightened out,
        // but it will be easier after T2217.
        $desc = "updated field '{$label}'";
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

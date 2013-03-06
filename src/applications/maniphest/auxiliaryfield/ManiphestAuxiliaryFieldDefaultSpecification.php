<?php

/**
 * @group maniphest
 */
class ManiphestAuxiliaryFieldDefaultSpecification
  extends ManiphestAuxiliaryFieldSpecification
  implements PhabricatorMarkupInterface {

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
  const TYPE_DESC   = 'description';
  const TYPE_PERSON = 'person';
  const TYPE_GEN    = 'generated';

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

  public function renderControl($user) {
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
        break;
      case self::TYPE_PERSON:
        $control = new AphrontFormTokenizerControl();
        id($control)->setDatasource('/typeahead/common/users/');
        break;
      case self::TYPE_DESC:
        $control = new PhabricatorRemarkupControl();
        break;
      case self::TYPE_GEN:
        $control = new AphrontFormStaticControl();
        break;
      default:
        $label = $this->getLabel();
        throw new ManiphestAuxiliaryFieldTypeException(
          "Field type '{$type}' is not a valid type (for field '{$label}').");
        break;
    }

    if ($type == self::TYPE_BOOL) {
      $control->addCheckbox(
        'auxiliary['.$this->getAuxiliaryKey().']',
        1,
        $this->getCheckboxLabel(),
        (bool)$this->getValue());
    } else if ($type == self::TYPE_DATE) {
      // FIXME: Hack to use readValueFromRequest for dates.
      $control
          ->setUser($user) // Required for timezone setting.
          ->setInitialTime(AphrontFormDateControl::TIME_START_OF_BUSINESS);
//          ->setValueToInitialTime();
      $control->setValue($this->getValue());
      $control->setName('auxiliary_'.$this->getAuxiliaryKey().'');
    } else {
      $control->setValue($this->getValue());
      $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
    }

    $control->setLabel($this->getLabel());
    $control->setCaption($this->getCaption());
    $control->setError($this->getError());
    $control->setDisabled($this->isReadonly());

    return $control;
  }

  public function renderSearchControls($user) {
    $controls = array();

    $type = $this->getFieldType();
    switch ($type) {
      case self::TYPE_INT:
        $control = new AphrontFormTextControl();
        $control->setLabel($this->getLabel());
        $control->setError($this->getError());
        $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
        $controls[] = $control;
        break;
      case self::TYPE_STRING:
        $control = new AphrontFormTextControl();
        $control->setLabel($this->getLabel());
        $control->setError($this->getError());
        $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
        $controls[] = $control;
        break;
      case self::TYPE_SELECT:
        $any_control = new AphrontFormTokenizerControl();
        $any_control->setLabel('Any ' . $this->getLabel());
        $any_control->setCaption('Find tasks with ANY of these values.');
        $any_control->setError($this->getError());
        $any_control->setName('auxiliary['.$this->getAuxiliaryKey().'_any]');
        $exclude_control = new AphrontFormTokenizerControl();
        $exclude_control->setLabel('Exclude ' . $this->getLabel());
        $exclude_control->setCaption('Find tasks with NONE of these values.');
        $exclude_control->setError($this->getError());
        $exclude_control->setName('auxiliary['.$this->getAuxiliaryKey().'_exclude]');
        id($any_control)->setDatasource('/typeahead/maniphest/custom-attribute/' . base64_encode($this->getAuxiliaryKey()) . '/');
        id($exclude_control)->setDatasource('/typeahead/maniphest/custom-attribute/' . base64_encode($this->getAuxiliaryKey()) . '/');
        $controls[] = $any_control;
        $controls[] = $exclude_control;
        break;
      case self::TYPE_BOOL:
        $control = new AphrontFormSelectControl();
        $control->setOptions(array('either' => 'Either', 'true' => 'Yes', 'false' => 'No'));
        $control->setLabel($this->getLabel());
        $control->setError($this->getError());
        $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
        $controls[] = $control;
        break;
      case self::TYPE_DATE:
        $use_control = new AphrontFormCheckboxControl();
        $use_control->addCheckbox(
          'auxiliary['.$this->getAuxiliaryKey().'_use]',
          1,
          'Filter ' . $this->getLabel() . ' between these dates.',
          false);
        $after_control = new AphrontFormDateControl();
        $after_control->setLabel('After ' . $this->getLabel());
        $after_control->setCaption('Find tasks AFTER this date.');
        $after_control->setError($this->getError());
        $after_control->setName('auxiliary['.$this->getAuxiliaryKey().'_after]');
        $before_control = new AphrontFormDateControl();
        $before_control->setLabel('Before ' . $this->getLabel());
        $before_control->setCaption('Find tasks BEFORE this date.');
        $before_control->setError($this->getError());
        $before_control->setName('auxiliary['.$this->getAuxiliaryKey().'_before]');
        id($after_control)
          ->setUser($user) // Required for timezone setting.
          ->setInitialTime(AphrontFormDateControl::TIME_START_OF_BUSINESS)
          ->setValueToInitialTime();
        id($before_control)
          ->setUser($user) // Required for timezone setting.
          ->setInitialTime(AphrontFormDateControl::TIME_END_OF_BUSINESS)
          ->setValueToInitialTime();
        $controls[] = $use_control;
        $controls[] = $after_control;
        $controls[] = $before_control;
        break;
      case self::TYPE_PERSON:
        $all_control = new AphrontFormTokenizerControl();
        $all_control->setLabel($this->getLabel());
        $all_control->setCaption('Find tasks with ALL of these people.');
        $all_control->setError($this->getError());
        $all_control->setName('auxiliary['.$this->getAuxiliaryKey().'_all]');
        $any_control = new AphrontFormTokenizerControl();
        $any_control->setLabel('Any ' . $this->getLabel());
        $any_control->setCaption('Find tasks with ANY of these people.');
        $any_control->setError($this->getError());
        $any_control->setName('auxiliary['.$this->getAuxiliaryKey().'_any]');
        $exclude_control = new AphrontFormTokenizerControl();
        $exclude_control->setLabel('Exclude ' . $this->getLabel());
        $exclude_control->setCaption('Find tasks with NONE of these people.');
        $exclude_control->setError($this->getError());
        $exclude_control->setName('auxiliary['.$this->getAuxiliaryKey().'_exclude]');
        id($all_control)->setDatasource('/typeahead/common/users/');
        id($any_control)->setDatasource('/typeahead/common/users/');
        id($exclude_control)->setDatasource('/typeahead/common/users/');
        $controls[] = $all_control;
        $controls[] = $any_control;
        $controls[] = $exclude_control;
        break;
      case self::TYPE_DESC:
        $control = new AphrontFormTextControl();
        $control->setLabel($this->getLabel());
        $control->setError($this->getError());
        $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
        $controls[] = $control;
        break;
      case self::TYPE_GEN:
        $control = new AphrontFormTextControl();
        $control->setLabel($this->getLabel());
        $control->setError($this->getError());
        $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
        $controls[] = $control;
        break;
      default:
        throw new ManiphestAuxiliaryFieldTypeException(
          "Field type '{$type}' is not a valid type (for field '{$label}').");
        break;
    }

    return $controls;
  }

  public function setValue($value) {
    // Array handling assumes you're imploding PHIDs, and thus
    // commas are valid seperators.
    // FIXME: There's gotta be a better way of doing this...
    if (!strncmp($value, '!!array!', strlen('!!array!'))) {
      $array = explode(',', substr($value, strlen('!!array!')));
      $new_array = array();
      for ($i = 0; $i < count($array); $i++) {
        if (!strncmp($array[$i], 'PHID-', strlen('PHID-'))) {
          $handles = id(new PhabricatorObjectHandleData(array($array[$i])))
            ->loadHandles();
          $new_array[$array[$i]] = $handles[$array[$i]]->getFullName();
        }
      }
      if (count($new_array) > 0) {
        return parent::setValue($new_array);
      } else {
        return parent::setValue($array);
      }
    } else {
      return parent::setValue($value);
    }
  }

  public function setValueFromRequest($request) {
    if ($this->getFieldType() == self::TYPE_DATE) {
      // FIXME: Hack to use readValueFromRequest for dates.
      $control = $this->renderControl($request->getUser());
      $control->setInitialTime(AphrontFormDateControl::TIME_START_OF_DAY);
      $control->setName('auxiliary_'.$this->getAuxiliaryKey().'');
      return $this->setValue($control->readValueFromRequest($request));
    } else {
      $aux_post_values = $request->getArr('auxiliary');
      $value = idx($aux_post_values, $this->getAuxiliaryKey(), '');
      if (is_array($value))
        $value = '!!array!' . implode(',', $value);
      return $this->setValue($value);
    }
  }

  public function getValueForStorage() {
    // Array handling assumes you're imploding PHIDs, and thus
    // commas are valid seperators.
    if (is_array($this->getValue())) {
      return '!!array!' . implode(',', array_keys($this->getValue()));
    } else {
      return $this->getValue();
    }
  }

  public function setValueFromStorage($value) {
    return $this->setValue($value);
  }

  private function renderLinksForArray($value) {
    // Array handling assumes you're imploding PHIDs, and thus
    // commas are valid seperators.
    // FIXME: There's gotta be a better way of doing this...
    $text = "";
    $first = true;
    if (is_array($value))
      $value = '!!array!' . implode(',', array_keys($value));
    if (!strncmp($value, '!!array!', strlen('!!array!'))) {
      $array = explode(',', substr($value, strlen('!!array!')));
      $new_array = array();
      for ($i = 0; $i < count($array); $i++) {
        if (!$first) $text .= ', ';
        $first = false;
        if (!strncmp($array[$i], 'PHID-', strlen('PHID-'))) {
          $handles = id(new PhabricatorObjectHandleData(array($array[$i])))
            ->loadHandles();
          $text .= $handles[$array[$i]]->renderLink();
        } else {
          $text .= $array[$i];
        }
      }
    } else {
      // FIXME: Maybe throw an exception here since value is not an array?
      $text .= $value;
    }
    return $text;
  }

  public function validate() {
    switch ($this->getFieldType()) {
      case self::TYPE_INT:
        if (!is_numeric($this->getValue())) {
          throw new ManiphestAuxiliaryFieldValidationException(
            $this->getLabel().' must be an integer value.'
          );
        }
        break;
      case self::TYPE_BOOL:
        return true;
      case self::TYPE_STRING:
        return true;
      case self::TYPE_SELECT:
        return true;
      case self::TYPE_DATE:
        // FIXME: Do actual validation.
        return true;
      case self::TYPE_PERSON:
        // FIXME: Do actual validation.
        return true;
      case self::TYPE_DESC:
        // FIXME: Do actual validation.
        return true;
      case self::TYPE_GEN:
        // FIXME: Do actual validation.
        return true;
    }
  }

  public function renderForDetailView(PhabricatorUser $user) {
    switch ($this->getFieldType()) {
      case self::TYPE_BOOL:
        if ($this->getValue()) {
          return phutil_escape_html($this->getCheckboxValue());
        } else {
          return null;
        }
      case self::TYPE_DATE:
        return phutil_escape_html(phabricator_date($this->getValue(), $user));
      case self::TYPE_SELECT:
        $display = idx($this->getSelectOptions(), $this->getValue());
        return $display;
      case self::TYPE_PERSON:
        return $this->renderLinksForArray($this->getValue());
    }
    return parent::renderForDetailView($user);
  }


  public function renderTransactionDescription(
    ManiphestTransaction $transaction,
    $target,
    $user) {

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
      case self::TYPE_DESC:
        // Don't include change information because the field might
        // be huge.
        if (!strlen($old)) {
          if (!strlen($new)) {
            return null;
          }
          $desc = "set field '{$label}'";
        } else {
          $desc = "updated '{$label}'";
        }
        break;
      case self::TYPE_PERSON:
        // FIXME: Actually handle this.
        if (!strlen($old)) {
          if (!strlen($new)) {
            return null;
          }
          $desc = "set field '{$label}'";
        } else {
          $desc = "updated '{$label}'";
        }
        break;
      case self::TYPE_DATE:
        if ($user == null) {
          if (!strlen($old)) {
            if (!strlen($new)) {
              return null;
            }
            $desc = "set field '{$label}'";
          } else {
            $desc = "updated '{$label}'";
          }
          return $desc;
        }
        if (!strlen($old)) {
          if (!strlen($new)) {
            return null;
          }
          $new_date = phabricator_date($new, $user);
          $desc = "set field '{$label}' to '{$new_date}'";
        } else {
          $old_date = phabricator_date($old, $user);
          $new_date = phabricator_date($new, $user);
          $desc = "updated '{$label}' ".
                  "from '{$old_date}' to '{$new_date}'";
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


/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * @task markup
   */
  public function getMarkupFieldKey($field) {
    $id = PhabricatorHash::digest($this->getMarkupText($field));
    return "maniphest:x:{$field}:{$id}";
  }


  /**
   * @task markup
   */
  public function getMarkupText($field) {
    return $this->getValue();
  }


  /**
   * @task markup
   */
  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newManiphestMarkupEngine();
  }


  /**
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return false;
  }

}

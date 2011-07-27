<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group maniphest
 */
class ManiphestAuxiliaryFieldDefaultSpecification
  extends ManiphestAuxiliaryFieldSpecification {

  private $required;
  private $fieldType;

  private $selectOptions;
  private $error;

  const TYPE_SELECT = 'select';
  const TYPE_STRING = 'string';
  const TYPE_INT    = 'int';

  public function getFieldType() {
    return $this->fieldType;
  }

  public function setFieldType($val) {
    $this->fieldType = $val;
  }

  public function getError() {
    return $this->error;
  }

  public function setError($val) {
    $this->error = $val;
  }

  public function getSelectOptions() {
    return $this->selectOptions;
  }

  public function setSelectOptions($array) {
    $this->selectOptions = $array;
  }

  public function setRequired($bool) {
    $this->required = $bool;
  }

  public function isRequired() {
    return $this->required;
  }

  public function renderControl() {
    $control = null;

    switch ($this->getFieldType()) {
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

      default:
        throw new ManiphestAuxiliaryFieldTypeException(
          $this->getFieldType().' is not a valid type for '.$this->getLabel()
        );
        break;
    }

    $control->setValue($this->getValue());
    $control->setLabel($this->getLabel());
    $control->setName('auxiliary['.$this->getAuxiliaryKey().']');
    $control->setError($this->getError());

    return $control;
  }

  public function setValueFromRequest($request) {
    $aux_post_values = $request->getArr('auxiliary');

    $this->setValue(
      $aux_post_values[$this->getAuxiliaryKey()]
    );
  }

  public function getValueForStorage() {
    return $this->getValue();
  }

  public function setValueFromStorage($value) {
    $this->setValue($value);
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

      case self::TYPE_STRING:
        return true;
        break;

      case self::TYPE_SELECT:
        return true;
        break;
    }
  }

}

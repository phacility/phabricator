<?php

abstract class PhabricatorTokenizerEditField
  extends PhabricatorEditField {

  private $originalValue;

  abstract protected function newDatasource();

  protected function newControl() {
    $control = id(new AphrontFormTokenizerControl())
      ->setDatasource($this->newDatasource());

    if ($this->originalValue !== null) {
      $control->setOriginalValue($this->originalValue);
    }

    return $control;
  }

  public function setValue($value) {
    $this->originalValue = $value;
    return parent::setValue($value);
  }

  protected function getValueFromSubmit(AphrontRequest $request, $key) {
    // TODO: Maybe move this unusual read somewhere else so subclassing this
    // correctly is easier?
    $this->originalValue = $request->getArr($key.'.original');

    return parent::getValueFromSubmit($request, $key);
  }

  protected function getValueForTransaction() {
    $new = parent::getValueForTransaction();

    $edge_types = array(
      PhabricatorTransactions::TYPE_EDGE => true,
      PhabricatorTransactions::TYPE_SUBSCRIBERS => true,
    );

    if (isset($edge_types[$this->getTransactionType()])) {
      if ($this->originalValue !== null) {
        // If we're building an edge transaction and the request has data
        // about the original value the user saw when they loaded the form,
        // interpret the edit as a mixture of "+" and "-" operations instead
        // of a single "=" operation. This limits our exposure to race
        // conditions by making most concurrent edits merge correctly.

        $new = parent::getValueForTransaction();
        $old = $this->originalValue;

        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        $value = array();

        if ($add) {
          $value['+'] = array_fuse($add);
        }
        if ($rem) {
          $value['-'] = array_fuse($rem);
        }

        return $value;
      } else {

        if (!is_array($new)) {
          throw new Exception(print_r($new, true));
        }

        return array(
          '=' => array_fuse($new),
        );
      }
    }

    return $new;
  }

  protected function newHTTPParameterType() {
    return new AphrontPHIDListHTTPParameterType();
  }

}

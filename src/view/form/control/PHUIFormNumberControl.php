<?php

final class PHUIFormNumberControl extends AphrontFormControl {

  private $disableAutocomplete;

  public function setDisableAutocomplete($disable_autocomplete) {
    $this->disableAutocomplete = $disable_autocomplete;
    return $this;
  }

  public function getDisableAutocomplete() {
    return $this->disableAutocomplete;
  }

  protected function getCustomControlClass() {
    return 'phui-form-number';
  }

  protected function renderInput() {
    if ($this->getDisableAutocomplete()) {
      $autocomplete = 'off';
    } else {
      $autocomplete = null;
    }

    return javelin_tag(
      'input',
      array(
        'type' => 'text',
        'pattern' => '\d*',
        'name' => $this->getName(),
        'value' => $this->getValue(),
        'disabled' => $this->getDisabled() ? 'disabled' : null,
        'autocomplete' => $autocomplete,
        'id' => $this->getID(),
      ));
  }

}

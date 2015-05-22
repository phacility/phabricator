<?php

final class AphrontFormPasswordControl extends AphrontFormControl {

  private $disableAutocomplete;

  public function setDisableAutocomplete($disable_autocomplete) {
    $this->disableAutocomplete = $disable_autocomplete;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-password';
  }

  protected function renderInput() {
    return phutil_tag(
      'input',
      array(
        'type'         => 'password',
        'name'         => $this->getName(),
        'value'        => $this->getValue(),
        'disabled'     => $this->getDisabled() ? 'disabled' : null,
        'autocomplete' => ($this->disableAutocomplete ? 'off' : null),
        'id'           => $this->getID(),
      ));
  }

}

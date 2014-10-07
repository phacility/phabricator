<?php

final class AphrontFormTextControl extends AphrontFormControl {

  private $disableAutocomplete;
  private $sigil;
  private $placeholder;

  public function setDisableAutocomplete($disable) {
    $this->disableAutocomplete = $disable;
    return $this;
  }

  private function getDisableAutocomplete() {
    return $this->disableAutocomplete;
  }

  public function getPlaceholder() {
    return $this->placeholder;
  }

  public function setPlaceholder($placeholder) {
    $this->placeholder = $placeholder;
    return $this;
  }

  public function getSigil() {
    return $this->sigil;
  }

  public function setSigil($sigil) {
    $this->sigil = $sigil;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-text';
  }

  protected function renderInput() {
    return javelin_tag(
      'input',
      array(
        'type'         => 'text',
        'name'         => $this->getName(),
        'value'        => $this->getValue(),
        'disabled'     => $this->getDisabled() ? 'disabled' : null,
        'autocomplete' => $this->getDisableAutocomplete() ? 'off' : null,
        'id'           => $this->getID(),
        'sigil'        => $this->getSigil(),
        'placeholder'  => $this->getPlaceholder(),
      ));
  }

}

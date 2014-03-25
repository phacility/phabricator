<?php

final class AphrontFormSubmitControl extends AphrontFormControl {

  private $cancelButton;

  public function addCancelButton($href, $label = null) {
    if (!$label) {
      $label = pht('Cancel');
    }

    $this->cancelButton = phutil_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'button grey',
      ),
      $label);
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-submit';
  }

  protected function renderInput() {
    $submit_button = null;
    if ($this->getValue()) {
      $submit_button = phutil_tag(
        'button',
        array(
          'type'      => 'submit',
          'name'      => '__submit__',
          'disabled'  => $this->getDisabled() ? 'disabled' : null,
        ),
        $this->getValue());
    }

    return array(
      $submit_button,
      $this->cancelButton,
    );
  }

}

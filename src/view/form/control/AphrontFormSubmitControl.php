<?php

final class AphrontFormSubmitControl extends AphrontFormControl {

  protected $cancelButton;

  public function addCancelButton($href, $label = 'Cancel') {
    $this->cancelButton = phutil_render_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'button grey',
      ),
      phutil_escape_html($label));
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-submit';
  }

  protected function renderInput() {
    $submit_button = null;
    if ($this->getValue()) {
      $submit_button = phutil_render_tag(
        'button',
        array(
          'name'      => '__submit__',
          'disabled'  => $this->getDisabled() ? 'disabled' : null,
        ),
        phutil_escape_html($this->getValue()));
    }
    return $submit_button.$this->cancelButton;
  }

}

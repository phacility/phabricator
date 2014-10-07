<?php

final class AphrontFormTextWithSubmitControl extends AphrontFormControl {

  private $submitLabel;

  public function setSubmitLabel($submit_label) {
    $this->submitLabel = $submit_label;
    return $this;
  }

  public function getSubmitLabel() {
    return $this->submitLabel;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-text-with-submit';
  }

  protected function renderInput() {
    return phutil_tag(
      'div',
      array(
        'class' => 'text-with-submit-control-outer-bounds',
      ),
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'text-with-submit-control-text-bounds',
          ),
          javelin_tag(
            'input',
            array(
              'type'      => 'text',
              'class'     => 'text-with-submit-control-text',
              'name'      => $this->getName(),
              'value'     => $this->getValue(),
              'disabled'  => $this->getDisabled() ? 'disabled' : null,
              'id'        => $this->getID(),
            ))),
        phutil_tag(
          'div',
          array(
            'class' => 'text-with-submit-control-submit-bounds',
          ),
          javelin_tag(
            'input',
            array(
              'type' => 'submit',
              'class' => 'text-with-submit-control-submit grey',
              'value' => coalesce($this->getSubmitLabel(), pht('Submit')),
            ))),
      ));
  }

}

<?php

final class ConpherencePontificateControl extends AphrontFormControl {

  private $formID;

  public function setFormID($form_id) {
    $this->formID = $form_id;
    return $this;
  }
  public function getFormID() {
    return $this->formID;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-submit';
  }

  protected function renderInput() {

    return javelin_tag(
      'button',
      array (
        'sigil' => 'conpherence-pontificate',
      ),
      pht('Pontificate'));
  }

}

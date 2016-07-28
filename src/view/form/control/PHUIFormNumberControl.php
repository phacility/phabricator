<?php

final class PHUIFormNumberControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'phui-form-number';
  }

  protected function renderInput() {
    return javelin_tag(
      'input',
      array(
        'type' => 'text',
        'pattern' => '\d*',
        'name' => $this->getName(),
        'value' => $this->getValue(),
        'disabled' => $this->getDisabled() ? 'disabled' : null,
        'id' => $this->getID(),
      ));
  }

}

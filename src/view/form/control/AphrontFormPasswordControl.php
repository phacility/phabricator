<?php

final class AphrontFormPasswordControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-password';
  }

  protected function renderInput() {
    return phutil_render_tag(
      'input',
      array(
        'type'      => 'password',
        'name'      => $this->getName(),
        'value'     => $this->getValue(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
        'id'        => $this->getID(),
      ));
  }

}

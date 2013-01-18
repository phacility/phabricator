<?php

final class AphrontFormFileControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-file-text';
  }

  protected function renderInput() {
    return phutil_tag(
      'input',
      array(
        'type'      => 'file',
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
      ));
  }

}

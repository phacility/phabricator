<?php

final class AphrontFormStaticControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-static';
  }

  protected function renderInput() {
    return phutil_escape_html($this->getValue());
  }

}

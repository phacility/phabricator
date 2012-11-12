<?php

final class AphrontFormMarkupControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-markup';
  }

  protected function renderInput() {
    return $this->getValue();
  }

}

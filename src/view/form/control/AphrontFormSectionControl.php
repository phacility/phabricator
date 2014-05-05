<?php

final class AphrontFormSectionControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-section';
  }

  protected function renderInput() {
    return $this->getValue();
  }

}

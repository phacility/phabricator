<?php

final class AphrontFormDividerControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-divider';
  }

  protected function renderInput() {
    return phutil_tag('hr');
  }

}

<?php

final class PHUIFormDividerControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'phui-form-divider';
  }

  protected function renderInput() {
    return phutil_tag('hr', array());
  }

}

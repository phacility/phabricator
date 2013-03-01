<?php

final class AphrontFormImageControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-image';
  }

  protected function renderInput() {
    $id = celerity_generate_unique_node_id();

    return hsprintf(
      '%s<div style="clear: both;">%s%s</div>',
      phutil_tag(
        'input',
        array(
          'type'  => 'file',
          'name'  => $this->getName(),
        )),
      phutil_tag(
        'input',
        array(
          'type'  => 'checkbox',
          'name'  => 'default_image',
          'class' => 'default-image',
          'id'    => $id,
        )),
      phutil_tag(
        'label',
        array(
          'for' => $id,
        ),
        pht('Use Default Image instead')));
  }

}

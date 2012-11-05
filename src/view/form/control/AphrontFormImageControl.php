<?php

final class AphrontFormImageControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-image';
  }

  protected function renderInput() {
    $id = celerity_generate_unique_node_id();

    return
      phutil_render_tag(
        'input',
        array(
          'type'  => 'file',
          'name'  => $this->getName(),
          'class' => 'image',
        )).
      '<div style="clear: both;">'.
      phutil_render_tag(
        'input',
        array(
          'type'  => 'checkbox',
          'name'  => 'default_image',
          'class' => 'default-image',
          'id'    => $id,
        )).
      phutil_render_tag(
        'label',
        array(
          'for' => $id,
        ),
        'Use Default Image instead').
      '</div>';
  }

}

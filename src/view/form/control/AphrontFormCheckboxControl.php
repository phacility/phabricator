<?php

final class AphrontFormCheckboxControl extends AphrontFormControl {

  private $boxes = array();

  public function addCheckbox(
    $name,
    $value,
    $label,
    $checked = false,
    $id = null) {
    $this->boxes[] = array(
      'name'    => $name,
      'value'   => $value,
      'label'   => $label,
      'checked' => $checked,
      'id'      => $id,
    );
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-checkbox';
  }

  protected function renderInput() {
    $rows = array();
    foreach ($this->boxes as $box) {
      $id = idx($box, 'id');
      if ($id === null) {
        $id = celerity_generate_unique_node_id();
      }
      $checkbox = phutil_tag(
        'input',
        array(
          'id' => $id,
          'type' => 'checkbox',
          'name' => $box['name'],
          'value' => $box['value'],
          'checked' => $box['checked'] ? 'checked' : null,
          'disabled' => $this->getDisabled() ? 'disabled' : null,
        ));
      $label = phutil_tag(
        'label',
        array(
          'for' => $id,
        ),
        $box['label']);
      $rows[] = phutil_tag('tr', array(), array(
        phutil_tag('td', array(), $checkbox),
        phutil_tag('th', array(), $label),
      ));
    }
    return phutil_tag(
      'table',
      array('class' => 'aphront-form-control-checkbox-layout'),
      $rows);
  }

}

<?php

final class AphrontFormCheckboxControl extends AphrontFormControl {

  private $boxes = array();

  public function addCheckbox($name, $value, $label, $checked = false) {
    $this->boxes[] = array(
      'name'    => $name,
      'value'   => $value,
      'label'   => $label,
      'checked' => $checked,
    );
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-checkbox';
  }

  protected function renderInput() {
    $rows = array();
    foreach ($this->boxes as $box) {
      $id = celerity_generate_unique_node_id();
      $checkbox = phutil_render_tag(
        'input',
        array(
          'id' => $id,
          'type' => 'checkbox',
          'name' => $box['name'],
          'value' => $box['value'],
          'checked' => $box['checked'] ? 'checked' : null,
          'disabled' => $this->getDisabled() ? 'disabled' : null,
        ));
      $label = phutil_render_tag(
        'label',
        array(
          'for' => $id,
        ),
        phutil_escape_html($box['label']));
      $rows[] =
        '<tr>'.
          '<td>'.$checkbox.'</td>'.
          '<th>'.$label.'</th>'.
        '</tr>';
    }
    return
      '<table class="aphront-form-control-checkbox-layout">'.
        implode("\n", $rows).
      '</table>';
  }

}

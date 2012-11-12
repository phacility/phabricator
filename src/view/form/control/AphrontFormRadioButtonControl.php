<?php

final class AphrontFormRadioButtonControl extends AphrontFormControl {

  private $buttons = array();

  public function addButton($value, $label, $caption, $class = null) {
    $this->buttons[] = array(
      'value'   => $value,
      'label'   => $label,
      'caption' => $caption,
      'class' => $class,
    );
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-radio';
  }

  protected function renderInput() {
    $rows = array();
    foreach ($this->buttons as $button) {
      $id = celerity_generate_unique_node_id();
      $radio = phutil_render_tag(
        'input',
        array(
          'id' => $id,
          'type' => 'radio',
          'name' => $this->getName(),
          'value' => $button['value'],
          'checked' => ($button['value'] == $this->getValue())
            ? 'checked'
            : null,
          'disabled' => $this->getDisabled() ? 'disabled' : null,
        ));
      $label = phutil_render_tag(
        'label',
        array(
          'for' => $id,
          'class' => $button['class'],
        ),
        phutil_escape_html($button['label']));

      if (strlen($button['caption'])) {
        $label .=
          '<div class="aphront-form-radio-caption">'.
            phutil_escape_html($button['caption']).
          '</div>';
      }
      $rows[] =
        '<tr>'.
          '<td>'.$radio.'</td>'.
          '<th>'.$label.'</th>'.
        '</tr>';
    }

    return
      '<table class="aphront-form-control-radio-layout">'.
        implode("\n", $rows).
      '</table>';
  }

}

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
      $radio = phutil_tag(
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
      $label = phutil_tag(
        'label',
        array(
          'for' => $id,
          'class' => $button['class'],
        ),
        $button['label']);

      if (strlen($button['caption'])) {
        $label = hsprintf(
          '%s<div class="aphront-form-radio-caption">%s</div>',
          $label,
          $button['caption']);
      }
      $rows[] = hsprintf(
        '<tr><td>%s</td><th>%s</th></tr>',
        $radio,
        $label);
    }

    return phutil_tag(
      'table',
      array('class' => 'aphront-form-control-radio-layout'),
      $rows);
  }

}

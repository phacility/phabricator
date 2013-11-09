<?php

final class AphrontFormRadioButtonControl extends AphrontFormControl {

  private $buttons = array();

  public function addButton(
    $value,
    $label,
    $caption,
    $class = null,
    $disabled = false) {
    $this->buttons[] = array(
      'value'   => $value,
      'label'   => $label,
      'caption' => $caption,
      'class' => $class,
      'disabled' => $disabled,
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
          'disabled' => ($this->getDisabled() || $button['disabled'])
            ? 'disabled'
            : null,
        ));
      $label = phutil_tag(
        'label',
        array(
          'for' => $id,
          'class' => $button['class'],
        ),
        $button['label']);

      if ($button['caption']) {
        $label = array(
          $label,
          phutil_tag_div('aphront-form-radio-caption', $button['caption']),
        );
      }
      $rows[] = phutil_tag('tr', array(), array(
        phutil_tag('td', array(), $radio),
        phutil_tag('th', array(), $label),
      ));
    }

    return phutil_tag(
      'table',
      array('class' => 'aphront-form-control-radio-layout'),
      $rows);
  }

}

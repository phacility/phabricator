<?php

final class PHUIFormMultiSubmitControl extends AphrontFormControl {

  private $buttons = array();

  public function addBackButton($label = null) {
    if ($label === null) {
      $label = pht("\xC2\xAB Back");
    }
    return $this->addButton('__back__', $label, 'grey');
  }

  public function addSubmitButton($label) {
    return $this->addButton('__submit__', $label);
  }

  public function addCancelButton($uri, $label = null) {
    if ($label === null) {
      $label = pht('Cancel');
    }

    $this->buttons[] = phutil_tag(
      'a',
      array(
        'class' => 'grey button',
        'href' => $uri,
      ),
      $label);

    return $this;
  }

  public function addButton($name, $label, $class = null) {
    $this->buttons[] = phutil_tag(
      'input',
      array(
        'type'  => 'submit',
        'name'  => $name,
        'value' => $label,
        'class' => $class,
        'disabled' => $this->getDisabled() ? 'disabled' : null,
      ));
  }

  protected function getCustomControlClass() {
    return 'phui-form-control-multi-submit';
  }

  protected function renderInput() {
    return array_reverse($this->buttons);
  }

}

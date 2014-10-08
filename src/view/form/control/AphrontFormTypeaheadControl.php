<?php

final class AphrontFormTypeaheadControl extends AphrontFormControl {

  private $hardpointID;

  public function setHardpointID($hardpoint_id) {
    $this->hardpointID = $hardpoint_id;
    return $this;
  }

  public function getHardpointID() {
    return $this->hardpointID;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-typeahead';
  }

  protected function renderInput() {
    return javelin_tag(
      'div',
      array(
        'style' => 'position: relative;',
        'id' => $this->getHardpointID(),
      ),
      javelin_tag(
        'input',
        array(
          'type'         => 'text',
          'name'         => $this->getName(),
          'value'        => $this->getValue(),
          'disabled'     => $this->getDisabled() ? 'disabled' : null,
          'autocomplete' => 'off',
          'id'           => $this->getID(),
        )));
  }

}

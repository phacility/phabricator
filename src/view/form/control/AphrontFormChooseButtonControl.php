<?php

final class AphrontFormChooseButtonControl extends AphrontFormControl {

  private $displayValue;
  private $buttonText;
  private $chooseURI;

  public function setDisplayValue($display_value) {
    $this->displayValue = $display_value;
    return $this;
  }

  public function getDisplayValue() {
    return $this->displayValue;
  }

  public function setButtonText($text) {
    $this->buttonText = $text;
    return $this;
  }

  public function setChooseURI($choose_uri) {
    $this->chooseURI = $choose_uri;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-choose-button';
  }

  protected function renderInput() {
    Javelin::initBehavior('choose-control');

    $input_id = celerity_generate_unique_node_id();
    $display_id = celerity_generate_unique_node_id();

    $display_value = $this->displayValue;
    $button = javelin_tag(
      'a',
      array(
        'href' => '#',
        'class' => 'button grey',
        'sigil' => 'aphront-form-choose-button',
      ),
      nonempty($this->buttonText, pht('Choose...')));

    $display_cell = phutil_tag(
      'td',
      array(
        'class' => 'aphront-form-choose-display-cell',
        'id' => $display_id,
      ),
      $display_value);

    $button_cell = phutil_tag(
      'td',
      array(
        'class' => 'aphront-form-choose-button-cell',
      ),
      $button);

    $row = phutil_tag(
      'tr',
      array(),
      array($display_cell, $button_cell));

    $layout = javelin_tag(
      'table',
      array(
        'class' => 'aphront-form-choose-table',
        'sigil' => 'aphront-form-choose',
        'meta' => array(
          'uri' => $this->chooseURI,
          'inputID' => $input_id,
          'displayID' => $display_id,
        ),
      ),
      $row);

    $hidden_input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => $this->getName(),
        'value' => $this->getValue(),
        'id' => $input_id,
      ));

    return array(
      $hidden_input,
      $layout,
    );
  }

}

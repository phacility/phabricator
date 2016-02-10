<?php

final class PHUIFormIconSetControl
  extends AphrontFormControl {

  private $iconSet;

  public function setIconSet(PhabricatorIconSet $icon_set) {
    $this->iconSet = $icon_set;
    return $this;
  }

  public function getIconSet() {
    return $this->iconSet;
  }

  protected function getCustomControlClass() {
    return 'phui-form-iconset-control';
  }

  protected function renderInput() {
    Javelin::initBehavior('choose-control');

    $set = $this->getIconSet();

    $input_id = celerity_generate_unique_node_id();
    $display_id = celerity_generate_unique_node_id();

    $is_disabled = $this->getDisabled();

    $classes = array();
    $classes[] = 'button';
    $classes[] = 'grey';

    if ($is_disabled) {
      $classes[] = 'disabled';
    }

    $button = javelin_tag(
      'a',
      array(
        'href' => '#',
        'class' => implode(' ', $classes),
        'sigil' => 'phui-form-iconset-button',
      ),
      $set->getChooseButtonText());

    $icon = $set->getIcon($this->getValue());
    if ($icon) {
      $display = $set->renderIconForControl($icon);
    } else {
      $display = null;
    }

    $display_cell = phutil_tag(
      'td',
      array(
        'class' => 'phui-form-iconset-display-cell',
        'id' => $display_id,
      ),
      $display);

    $button_cell = phutil_tag(
      'td',
      array(
        'class' => 'phui-form-iconset-button-cell',
      ),
      $button);

    $row = phutil_tag(
      'tr',
      array(),
      array($display_cell, $button_cell));

    $layout = javelin_tag(
      'table',
      array(
        'class' => 'phui-form-iconset-table',
        'sigil' => 'phui-form-iconset',
        'meta' => array(
          'uri' => $set->getSelectURI(),
          'inputID' => $input_id,
          'displayID' => $display_id,
        ),
      ),
      $row);

    $hidden_input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'disabled' => ($is_disabled ? 'disabled' : null),
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

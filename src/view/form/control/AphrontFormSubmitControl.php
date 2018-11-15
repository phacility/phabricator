<?php

final class AphrontFormSubmitControl extends AphrontFormControl {

  private $buttons = array();
  private $sigils = array();

  public function addCancelButton($href, $label = null) {
    if (!$label) {
      $label = pht('Cancel');
    }
    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($href)
      ->setText($label)
      ->setColor(PHUIButtonView::GREY);
    $this->addButton($button);
    return $this;
  }

  public function addButton(PHUIButtonView $button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function addSigil($sigil) {
    $this->sigils[] = $sigil;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-submit';
  }

  protected function renderInput() {
    $submit_button = null;
    if ($this->getValue()) {

      if ($this->sigils) {
        $sigils = $this->sigils;
      } else {
        $sigils = null;
      }

      $submit_button = javelin_tag(
        'button',
        array(
          'type'      => 'submit',
          'name'      => '__submit__',
          'sigil' => $sigils,
          'disabled'  => $this->getDisabled() ? 'disabled' : null,
        ),
        $this->getValue());
    }

    return array(
      $submit_button,
      $this->buttons,
    );
  }

}

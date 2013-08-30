<?php

final class PHUIFormBoxView extends AphrontView {

  private $headerText;
  private $formError = null;
  private $form;

  public function setHeaderText($text) {
    $this->headerText = $text;
    return $this;
  }

  public function setFormError($error) {
    $this->formError = $error;
    return $this;
  }

  public function setForm($form) {
    $this->form = $form;
    return $this;
  }

  public function render() {

    $error = $this->formError ? $this->formError : null;

    $header = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle($this->headerText)
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_LIGHTBLUE);

    $content = id(new PHUIBoxView())
      ->appendChild(array($header, $error, $this->form))
      ->setBorder(true)
      ->addMargin(PHUI::MARGIN_LARGE_TOP)
      ->addMargin(PHUI::MARGIN_LARGE_LEFT)
      ->addMargin(PHUI::MARGIN_LARGE_RIGHT)
      ->addClass('phui-form-box');

    return $content;

  }
}

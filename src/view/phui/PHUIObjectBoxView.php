<?php

final class PHUIObjectBoxView extends AphrontView {

  private $headerText;
  private $formError = null;
  private $form;
  private $validationException;
  private $content = array();

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

  public function addContent($content) {
    $this->content[] = $content;
    return $this;
  }

  public function setValidationException(
    PhabricatorApplicationTransactionValidationException $ex = null) {
    $this->validationException = $ex;
    return $this;
  }

  public function render() {

    $header = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle($this->headerText)
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_LIGHTBLUE);

    $ex = $this->validationException;
    $exception_errors = null;
    if ($ex) {
      $messages = array();
      foreach ($ex->getErrors() as $error) {
        $messages[] = $error->getMessage();
      }
      if ($messages) {
        $exception_errors = id(new AphrontErrorView())
          ->setErrors($messages);
      }
    }

    $content = id(new PHUIBoxView())
      ->appendChild(
        array(
          $header,
          $this->formError,
          $exception_errors,
          $this->form,
          $this->content,
        ))
      ->setBorder(true)
      ->addMargin(PHUI::MARGIN_LARGE_TOP)
      ->addMargin(PHUI::MARGIN_LARGE_LEFT)
      ->addMargin(PHUI::MARGIN_LARGE_RIGHT)
      ->addClass('phui-object-box');

    return $content;

  }
}

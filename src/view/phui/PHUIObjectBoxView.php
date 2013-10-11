<?php

final class PHUIObjectBoxView extends AphrontView {

  private $headerText;
  private $formError = null;
  private $form;
  private $validationException;
  private $header;
  private $flush;
  private $propertyList = array();

  // This is mostly a conveinence method to lessen code dupe
  // when building objectboxes.
  public function addPropertyList(PHUIPropertyListView $property_list) {
    $this->propertyList[] = $property_list;
    return $this;
  }

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

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setFlush($flush) {
    $this->flush = $flush;
    return $this;
  }

  public function setValidationException(
    PhabricatorApplicationTransactionValidationException $ex = null) {
    $this->validationException = $ex;
    return $this;
  }

  public function render() {

    require_celerity_resource('phui-object-box-css');

    if ($this->header) {
      $header = $this->header;
      $header->setGradient(PhabricatorActionHeaderView::HEADER_LIGHTBLUE);
    } else {
      $header = id(new PHUIHeaderView())
        ->setHeader($this->headerText)
        ->setGradient(PhabricatorActionHeaderView::HEADER_LIGHTBLUE);
    }

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

    $property_list = null;
    if ($this->propertyList) {
      $property_list = new PHUIPropertyGroupView();
      foreach ($this->propertyList as $item) {
        $property_list->addPropertyList($item);
      }
    }

    $content = id(new PHUIBoxView())
      ->appendChild(
        array(
          $header,
          $this->formError,
          $exception_errors,
          $this->form,
          $property_list,
          $this->renderChildren(),
        ))
      ->setBorder(true)
      ->addMargin(PHUI::MARGIN_LARGE_TOP)
      ->addMargin(PHUI::MARGIN_LARGE_LEFT)
      ->addMargin(PHUI::MARGIN_LARGE_RIGHT)
      ->addClass('phui-object-box');

    if ($this->flush) {
      $content->addClass('phui-object-box-flush');
    }

    return $content;
  }
}

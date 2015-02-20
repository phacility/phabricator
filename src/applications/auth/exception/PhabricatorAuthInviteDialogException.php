<?php

abstract class PhabricatorAuthInviteDialogException
  extends PhabricatorAuthInviteException {

  private $title;
  private $body;
  private $submitButtonText;
  private $submitButtonURI;
  private $cancelButtonText;
  private $cancelButtonURI;

  public function __construct($title, $body) {
    $this->title = $title;
    $this->body = $body;
    parent::__construct(pht('%s: %s', $title, $body));
  }

  public function getTitle() {
    return $this->title;
  }

  public function getBody() {
    return $this->body;
  }

  public function setSubmitButtonText($submit_button_text) {
    $this->submitButtonText = $submit_button_text;
    return $this;
  }

  public function getSubmitButtonText() {
    return $this->submitButtonText;
  }

  public function setSubmitButtonURI($submit_button_uri) {
    $this->submitButtonURI = $submit_button_uri;
    return $this;
  }

  public function getSubmitButtonURI() {
    return $this->submitButtonURI;
  }

  public function setCancelButtonText($cancel_button_text) {
    $this->cancelButtonText = $cancel_button_text;
    return $this;
  }

  public function getCancelButtonText() {
    return $this->cancelButtonText;
  }

  public function setCancelButtonURI($cancel_button_uri) {
    $this->cancelButtonURI = $cancel_button_uri;
    return $this;
  }

  public function getCancelButtonURI() {
    return $this->cancelButtonURI;
  }

}

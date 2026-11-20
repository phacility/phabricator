<?php

final class PhortuneDisplayException
  extends Exception {

  private $view;

  public function setView($view) {
    $this->view = $view;
    return $this;
  }

  public function getView() {
    return $this->view;
  }

}

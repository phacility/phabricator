<?php

final class PhortuneDisplayException
  extends Exception {

  public function setView($view) {
    $this->view = $view;
    return $this;
  }

  public function getView() {
    return $this->view;
  }

}

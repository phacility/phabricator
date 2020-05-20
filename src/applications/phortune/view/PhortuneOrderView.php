<?php

abstract class PhortuneOrderView
  extends AphrontView {

  private $order;

  final public function setOrder(PhortuneCart $order) {
    $this->order = $order;
    return $this;
  }

  final public function getOrder() {
    return $this->order;
  }

}

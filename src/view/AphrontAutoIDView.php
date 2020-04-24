<?php

abstract class AphrontAutoIDView
  extends AphrontView {

  private $id;

  final public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

}

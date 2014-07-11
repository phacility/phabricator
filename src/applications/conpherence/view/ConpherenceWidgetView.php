<?php

abstract class ConpherenceWidgetView extends AphrontView {

  private $conpherence;
  private $updateURI;

  public function setUpdateURI($update_uri) {
    $this->updateURI = $update_uri;
    return $this;
  }
  public function getUpdateURI() {
    return $this->updateURI;
  }

  public function setConpherence(ConpherenceThread $conpherence) {
    $this->conpherence = $conpherence;
    return $this;
  }
  public function getConpherence() {
    return $this->conpherence;
  }

}

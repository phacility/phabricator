<?php

final class PhabricatorFerretMetadata extends Phobject {

  private $phid;
  private $engine;
  private $relevance;

  public function setEngine($engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->engine;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setRelevance($relevance) {
    $this->relevance = $relevance;
    return $this;
  }

  public function getRelevance() {
    return $this->relevance;
  }

  public function getRelevanceSortVector() {
    $engine = $this->getEngine();

    return id(new PhutilSortVector())
      ->addInt($engine->getObjectTypeRelevance())
      ->addInt(-$this->getRelevance());
  }

}

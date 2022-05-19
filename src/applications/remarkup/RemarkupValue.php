<?php

final class RemarkupValue
  extends Phobject {

  private $corpus;
  private $metadata;

  public function setCorpus($corpus) {
    $this->corpus = $corpus;
    return $this;
  }

  public function getCorpus() {
    return $this->corpus;
  }

  public function setMetadata(array $metadata) {
    $this->metadata = $metadata;
    return $this;
  }

  public function getMetadata() {
    return $this->metadata;
  }

}

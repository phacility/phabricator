<?php

final class DiffusionFileContent {

  private $corpus;

  final public function setCorpus($corpus) {
    $this->corpus = $corpus;
    return $this;
  }

  final public function getCorpus() {
    return $this->corpus;
  }

}

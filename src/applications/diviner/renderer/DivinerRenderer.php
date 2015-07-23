<?php

abstract class DivinerRenderer extends Phobject {

  private $publisher;
  private $atomStack = array();

  public function setPublisher($publisher) {
    $this->publisher = $publisher;
    return $this;
  }

  public function getPublisher() {
    return $this->publisher;
  }

  public function getConfig($key, $default = null) {
    return $this->getPublisher()->getConfig($key, $default);
  }

  protected function pushAtomStack(DivinerAtom $atom) {
    $this->atomStack[] = $atom;
    return $this;
  }

  protected function peekAtomStack() {
    return end($this->atomStack);
  }

  protected function popAtomStack() {
    array_pop($this->atomStack);
    return $this;
  }

  abstract public function renderAtom(DivinerAtom $atom);
  abstract public function renderAtomSummary(DivinerAtom $atom);
  abstract public function renderAtomIndex(array $refs);
  abstract public function getHrefForAtomRef(DivinerAtomRef $ref);

}

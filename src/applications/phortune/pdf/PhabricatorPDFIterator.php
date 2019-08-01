<?php

final class PhabricatorPDFIterator
  extends Phobject
  implements Iterator {

  private $generator;
  private $hasRewound;

  private $fragments;
  private $fragmentKey;
  private $fragmentBytes;
  private $fragmentOffsets = array();
  private $byteLength;

  public function setGenerator(PhabricatorPDFGenerator $generator) {
    if ($this->generator) {
      throw new Exception(
        pht(
          'This iterator already has a generator. You can not modify the '.
          'generator for a given iterator.'));
    }

    $this->generator = $generator;

    return $this;
  }

  public function getGenerator() {
    if (!$this->generator) {
      throw new Exception(
        pht(
          'This PDF iterator has no associated PDF generator.'));
    }

    return $this->generator;
  }

  public function getFragmentOffsets() {
    return $this->fragmentOffsets;
  }

  public function current() {
    return $this->fragmentBytes;
  }

  public function key() {
    return $this->framgentKey;
  }

  public function next() {
    $this->fragmentKey++;

    if (!$this->valid()) {
      return;
    }

    $fragment = $this->fragments[$this->fragmentKey];

    $this->fragmentOffsets[] = id(new PhabricatorPDFFragmentOffset())
      ->setFragment($fragment)
      ->setOffset($this->byteLength);

    $bytes = $fragment->getAsBytes();

    $this->fragmentBytes = $bytes;
    $this->byteLength += strlen($bytes);
  }

  public function rewind() {
    if ($this->hasRewound) {
      throw new Exception(
        pht(
          'PDF iterators may not be rewound. Create a new iterator to emit '.
          'another PDF.'));
    }

    $generator = $this->getGenerator();
    $objects = $generator->getObjects();

    $this->fragments = array();
    $this->fragments[] = new PhabricatorPDFHeadFragment();

    foreach ($objects as $object) {
      $this->fragments[] = $object;
    }

    $this->fragments[] = id(new PhabricatorPDFTailFragment())
      ->setIterator($this);

    $this->hasRewound = true;

    $this->fragmentKey = -1;
    $this->byteLength = 0;

    $this->next();
  }

  public function valid() {
    return isset($this->fragments[$this->fragmentKey]);
  }

}

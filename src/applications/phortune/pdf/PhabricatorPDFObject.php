<?php

abstract class PhabricatorPDFObject
  extends PhabricatorPDFFragment {

  private $generator;
  private $objectIndex;
  private $children = array();
  private $streams = array();

  final public function hasRefTableEntry() {
    return true;
  }

  final protected function writeFragment() {
    $this->writeLine('%d 0 obj', $this->getObjectIndex());
    $this->writeLine('<<');
    $this->writeObject();
    $this->writeLine('>>');

    $streams = $this->streams;
    $this->streams = array();
    foreach ($streams as $stream) {
      $this->writeLine('stream');
      $this->writeLine('%s', $stream);
      $this->writeLine('endstream');
    }

    $this->writeLine('endobj');
  }

  final public function setGenerator(
    PhabricatorPDFGenerator $generator,
    $index) {

    if ($this->getGenerator()) {
      throw new Exception(
        pht(
          'This PDF object is already registered with a PDF generator. You '.
          'can not register an object with more than one generator.'));
    }

    $this->generator = $generator;
    $this->objectIndex = $index;

    foreach ($this->getChildren() as $child) {
      $generator->addObject($child);
    }

    return $this;
  }

  final public function getGenerator() {
    return $this->generator;
  }

  final public function getObjectIndex() {
    if (!$this->objectIndex) {
      throw new Exception(
        pht(
          'Trying to get index for object ("%s") which has not been '.
          'registered with a generator.',
          get_class($this)));
    }

    return $this->objectIndex;
  }

  final protected function newChildObject(PhabricatorPDFObject $object) {
    if ($this->generator) {
      throw new Exception(
        pht(
          'Trying to add a new PDF Object child after already registering '.
          'the object with a generator.'));
    }

    $this->children[] = $object;
    return $object;
  }

  private function getChildren() {
    return $this->children;
  }

  abstract protected function writeObject();

  final protected function newStream($raw_data) {
    $stream_data = gzcompress($raw_data);

    $this->streams[] = $stream_data;

    return strlen($stream_data);
  }

}

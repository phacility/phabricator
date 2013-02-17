<?php

/**
 * Generate @{class:DivinerAtom}s from source code.
 */
abstract class DivinerAtomizer {

  private $book;
  private $fileName;

  /**
   * If you make a significant change to an atomizer, you can bump this
   * version to drop all the old atom caches.
   */
  public static function getAtomizerVersion() {
    return 1;
  }

  final public function atomize($file_name, $file_data) {
    $this->fileName = $file_name;
    return $this->executeAtomize($file_name, $file_data);
  }

  abstract protected function executeAtomize($file_name, $file_data);

  final public function setBook($book) {
    $this->book = $book;
    return $this;
  }

  final public function getBook() {
    return $this->book;
  }

  protected function newAtom($type) {
    return id(new DivinerAtom())
      ->setBook($this->getBook())
      ->setFile($this->fileName)
      ->setType($type);
  }

  protected function newRef($type, $name, $book = null, $context = null) {
    $book = coalesce($book, $this->getBook());

    return id(new DivinerAtomRef())
      ->setBook($book)
      ->setContext($context)
      ->setType($type)
      ->setName($name);
  }

}

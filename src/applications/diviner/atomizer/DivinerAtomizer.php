<?php

/**
 * Generate @{class:DivinerAtom}s from source code.
 */
abstract class DivinerAtomizer extends Phobject {

  private $book;
  private $fileName;
  private $atomContext;

  /**
   * If you make a significant change to an atomizer, you can bump this version
   * to drop all the old atom caches.
   */
  public static function getAtomizerVersion() {
    return 1;
  }

  final public function atomize($file_name, $file_data, array $context) {
    $this->fileName = $file_name;
    $this->atomContext = $context;
    $atoms = $this->executeAtomize($file_name, $file_data);

    // Promote the `@group` special to a property. If there's no `@group` on
    // an atom but the file it's in matches a group pattern, associate it with
    // the right group.
    foreach ($atoms as $atom) {
      $group = null;
      try {
        $group = $atom->getDocblockMetaValue('group');
      } catch (Exception $ex) {
        // There's no docblock metadata.
      }

      // If there's no group, but the file matches a group, use that group.
      if ($group === null && isset($context['group'])) {
        $group = $context['group'];
      }

      if ($group !== null) {
        $atom->setProperty('group', $group);
      }
    }

    return $atoms;
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

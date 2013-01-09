<?php

/**
 * Generate @{class:DivinerAtom}s from source code.
 */
abstract class DivinerAtomizer {

  private $project;

  /**
   * If you make a significant change to an atomizer, you can bump this
   * version to drop all the old atom caches.
   */
  public static function getAtomizerVersion() {
    return 1;
  }

  abstract public function atomize($file_name, $file_data);

  final public function setProject($project) {
    $this->project = $project;
    return $this;
  }

  final public function getProject() {
    return $this->project;
  }

  protected function newAtom($type) {
    return id(new DivinerAtom())
      ->setProject($this->getProject())
      ->setType($type);
  }

  protected function newRef($type, $name, $project = null, $context = null) {
    $project = coalesce($project, $this->getProject());

    return id(new DivinerAtomRef())
      ->setProject($project)
      ->setContext($context)
      ->setType($type)
      ->setName($name);
  }

}

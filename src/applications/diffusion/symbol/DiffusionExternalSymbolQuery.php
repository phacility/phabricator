<?php

final class DiffusionExternalSymbolQuery extends Phobject {

  private $languages = array();
  private $types = array();
  private $names = array();
  private $contexts = array();
  private $paths = array();
  private $lines = array();
  private $repositories = array();
  private $characterPositions = array();

  public function withLanguages(array $languages) {
    $this->languages = $languages;
    return $this;
  }

  public function withTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withContexts(array $contexts) {
    $this->contexts = $contexts;
    return $this;
  }

  public function withPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  public function withLines(array $lines) {
    $this->lines = $lines;
    return $this;
  }

  public function withCharacterPositions(array $positions) {
    $this->characterPositions = $positions;
    return $this;
  }

  public function withRepositories(array $repositories) {
    assert_instances_of($repositories, 'PhabricatorRepository');
    $this->repositories = $repositories;
    return $this;
  }

  public function getLanguages() {
    return $this->languages;
  }

  public function getTypes() {
    return $this->types;
  }

  public function getNames() {
    return $this->names;
  }

  public function getContexts() {
    return $this->contexts;
  }

  public function getPaths() {
    return $this->paths;
  }

  public function getLines() {
    return $this->lines;
  }

  public function getRepositories() {
    return $this->repositories;
  }

  public function getCharacterPositions() {
    return $this->characterPositions;
  }

  public function matchesAnyLanguage(array $languages) {
    return (!$this->languages) || array_intersect($languages, $this->languages);
  }

  public function matchesAnyType(array $types) {
    return (!$this->types) || array_intersect($types, $this->types);
  }
}

<?php

final class DiffusionExternalSymbolQuery {
  private $languages = array();
  private $types = array();
  private $names = array();
  private $contexts = array();

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

  public function matchesAnyLanguage(array $languages) {
    return (!$this->languages) || array_intersect($languages, $this->languages);
  }
  public function matchesAnyType(array $types) {
    return (!$this->types) || array_intersect($types, $this->types);
  }
}

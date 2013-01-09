<?php

final class DivinerAtomRef {

  private $project;
  private $context;
  private $type;
  private $name;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setContext($context) {
    $this->context = $context;
    return $this;
  }

  public function getContext() {
    return $this->context;
  }

  public function setProject($project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->project;
  }

  public function toDictionary() {
    return array(
      'project' => $this->getProject(),
      'context' => $this->getContext(),
      'type'    => $this->getType(),
      'name'    => $this->getName(),
    );
  }

  public function toHash() {
    $dict = $this->toDictionary();
    ksort($dict);
    return md5(serialize($dict)).'S';
  }

  public static function newFromDictionary(array $dict) {
    $obj = new DivinerAtomRef();
    $obj->project = idx($dict, 'project');
    $obj->context = idx($dict, 'context');
    $obj->type = idx($dict, 'type');
    $obj->name = idx($dict, 'name');
    return $obj;
  }
}

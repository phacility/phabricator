<?php

final class PhabricatorTypeaheadResult {

  private $name;
  private $uri;
  private $phid;
  private $priorityString;
  private $displayName;
  private $displayType;
  private $imageURI;
  private $priorityType;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function setPriorityString($priority_string) {
    $this->priorityString = $priority_string;
    return $this;
  }

  public function setDisplayName($display_name) {
    $this->displayName = $display_name;
    return $this;
  }

  public function setDisplayType($display_type) {
    $this->displayType = $display_type;
    return $this;
  }

  public function setImageURI($image_uri) {
    $this->imageURI = $image_uri;
    return $this;
  }

  public function setPriorityType($priority_type) {
    $this->priorityType = $priority_type;
    return $this;
  }

  public function getWireFormat() {
    $data = array(
      $this->name,
      $this->uri ? (string)$this->uri : null,
      $this->phid,
      $this->priorityString,
      $this->displayName,
      $this->displayType,
      $this->imageURI ? (string)$this->imageURI : null,
      $this->priorityType,
    );
    while (end($data) === null) {
      array_pop($data);
    }
    return $data;
  }

}


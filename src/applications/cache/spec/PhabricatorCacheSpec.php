<?php

abstract class PhabricatorCacheSpec extends Phobject {

  private $name;
  private $isEnabled = false;
  private $version;
  private $issues = array();

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIsEnabled($is_enabled) {
    $this->isEnabled = $is_enabled;
    return $this;
  }

  public function getIsEnabled() {
    return $this->isEnabled;
  }

  public function setVersion($version) {
    $this->version = $version;
    return $this;
  }

  public function getVersion() {
    return $this->version;
  }

  protected function newIssue($title, $body, $option = null) {
    $issue = array(
      'title' => $title,
      'body' => $body,
      'option' => $option,
    );

    $this->issues[] = $issue;

    return $issue;
  }

  public function getIssues() {
    return $this->issues;
  }

}

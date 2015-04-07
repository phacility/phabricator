<?php

abstract class PhabricatorCacheSpec extends Phobject {

  private $name;
  private $isEnabled = false;
  private $version;
  private $issues = array();

  private $usedMemory = 0;
  private $totalMemory = 0;
  private $entryCount = null;

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

  public function setUsedMemory($used_memory) {
    $this->usedMemory = $used_memory;
    return $this;
  }

  public function getUsedMemory() {
    return $this->usedMemory;
  }

  public function setTotalMemory($total_memory) {
    $this->totalMemory = $total_memory;
    return $this;
  }

  public function getTotalMemory() {
    return $this->totalMemory;
  }

  public function setEntryCount($entry_count) {
    $this->entryCount = $entry_count;
    return $this;
  }

  public function getEntryCount() {
    return $this->entryCount;
  }



}

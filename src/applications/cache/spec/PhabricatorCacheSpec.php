<?php

abstract class PhabricatorCacheSpec extends Phobject {

  private $name;
  private $isEnabled = false;
  private $version;
  private $clearCacheCallback = null;
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

  protected function newIssue($key) {
    $issue = id(new PhabricatorSetupIssue())
      ->setIssueKey($key);
    $this->issues[$key] = $issue;

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

  protected function raiseInstallAPCIssue() {
    $message = pht(
      "Installing the PHP extension 'APC' (Alternative PHP Cache) will ".
      "dramatically improve performance. Note that APC versions 3.1.14 and ".
      "3.1.15 are broken; 3.1.13 is recommended instead.");

    return $this
      ->newIssue('extension.apc')
      ->setShortName(pht('APC'))
      ->setName(pht("PHP Extension 'APC' Not Installed"))
      ->setMessage($message)
      ->addPHPExtension('apc');
  }

  protected function raiseEnableAPCIssue() {
    $summary = pht('Enabling APC/APCu will improve performance.');
    $message = pht(
      'The APC or APCu PHP extensions are installed, but not enabled in your '.
      'PHP configuration. Enabling these extensions will improve performance. '.
      'Edit the "%s" setting to enable these extensions.',
      'apc.enabled');

    return $this
      ->newIssue('extension.apc.enabled')
      ->setShortName(pht('APC/APCu Disabled'))
      ->setName(pht('APC/APCu Extensions Not Enabled'))
      ->setSummary($summary)
      ->setMessage($message)
      ->addPHPConfig('apc.enabled');
  }

  public function setClearCacheCallback($callback) {
    $this->clearCacheCallback = $callback;
    return $this;
  }

  public function getClearCacheCallback() {
    return $this->clearCacheCallback;
  }
}

<?php

final class PhabricatorSetupIssue {

  private $issueKey;
  private $name;
  private $message;
  private $isFatal;
  private $summary;
  private $shortName;

  private $isIgnored = false;
  private $phpExtensions = array();
  private $phabricatorConfig = array();
  private $relatedPhabricatorConfig = array();
  private $phpConfig = array();
  private $commands = array();

  public function addCommand($command) {
    $this->commands[] = $command;
    return $this;
  }

  public function getCommands() {
    return $this->commands;
  }

  public function setShortName($short_name) {
    $this->shortName = $short_name;
    return $this;
  }

  public function getShortName() {
    if ($this->shortName === null) {
      return $this->getName();
    }
    return $this->shortName;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    if ($this->summary === null) {
      return $this->getMessage();
    }
    return $this->summary;
  }

  public function setIssueKey($issue_key) {
    $this->issueKey = $issue_key;
    return $this;
  }

  public function getIssueKey() {
    return $this->issueKey;
  }

  public function setIsFatal($is_fatal) {
    $this->isFatal = $is_fatal;
    return $this;
  }

  public function getIsFatal() {
    return $this->isFatal;
  }

  public function addPHPConfig($php_config) {
    $this->phpConfig[] = $php_config;
    return $this;
  }

  public function getPHPConfig() {
    return $this->phpConfig;
  }

  public function addPhabricatorConfig($phabricator_config) {
    $this->phabricatorConfig[] = $phabricator_config;
    return $this;
  }

  public function getPhabricatorConfig() {
    return $this->phabricatorConfig;
  }

  public function addRelatedPhabricatorConfig($phabricator_config) {
    $this->relatedPhabricatorConfig[] = $phabricator_config;
    return $this;
  }

  public function getRelatedPhabricatorConfig() {
    return $this->relatedPhabricatorConfig;
  }

  public function addPHPExtension($php_extension) {
    $this->phpExtensions[] = $php_extension;
    return $this;
  }

  public function getPHPExtensions() {
    return $this->phpExtensions;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

  public function setIsIgnored($is_ignored) {
    $this->isIgnored = $is_ignored;
    return $this;
  }

  public function getIsIgnored() {
    return $this->isIgnored;
  }
}

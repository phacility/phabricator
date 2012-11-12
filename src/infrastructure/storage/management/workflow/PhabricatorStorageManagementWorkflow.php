<?php

abstract class PhabricatorStorageManagementWorkflow
  extends PhutilArgumentWorkflow {

  private $patches;
  private $api;

  public function setPatches(array $patches) {
    assert_instances_of($patches, 'PhabricatorStoragePatch');
    $this->patches = $patches;
    return $this;
  }

  public function getPatches() {
    return $this->patches;
  }

  final public function setAPI(PhabricatorStorageManagementAPI $api) {
    $this->api = $api;
    return $this;
  }

  final public function getAPI() {
    return $this->api;
  }

  public function isExecutable() {
    return true;
  }

}

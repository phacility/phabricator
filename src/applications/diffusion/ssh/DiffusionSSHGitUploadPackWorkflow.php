<?php

final class DiffusionSSHGitUploadPackWorkflow
  extends DiffusionSSHGitWorkflow {

  public function didConstruct() {
    $this->setName('git-upload-pack');
    $this->setArguments(
      array(
        array(
          'name'      => 'dir',
          'wildcard'  => true,
        ),
      ));
  }

  public function isReadOnly() {
    return true;
  }

  public function getRequestPath() {
    $args = $this->getArgs();
    return head($args->getArg('dir'));
  }

}

<?php

abstract class DiffusionSSHGitWorkflow extends DiffusionSSHWorkflow {

  protected function writeError($message) {
    // Git assumes we'll add our own newlines.
    return parent::writeError($message."\n");
  }

}

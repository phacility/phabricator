<?php

abstract class DiffusionSSHSubversionWorkflow extends DiffusionSSHWorkflow {

  protected function writeError($message) {
    // Subversion assumes we'll add our own newlines.
    return parent::writeError($message."\n");
  }

}

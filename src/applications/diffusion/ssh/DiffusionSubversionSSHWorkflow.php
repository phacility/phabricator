<?php

abstract class DiffusionSubversionSSHWorkflow extends DiffusionSSHWorkflow {

  protected function writeError($message) {
    // Subversion assumes we'll add our own newlines.
    return parent::writeError($message."\n");
  }

}

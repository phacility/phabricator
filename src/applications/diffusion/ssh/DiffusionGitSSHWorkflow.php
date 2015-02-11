<?php

abstract class DiffusionGitSSHWorkflow extends DiffusionSSHWorkflow {

  protected function writeError($message) {
    // Git assumes we'll add our own newlines.
    return parent::writeError($message."\n");
  }

  protected function identifyRepository() {
    $args = $this->getArgs();
    $path = head($args->getArg('dir'));
    return $this->loadRepositoryWithPath($path);
  }

  protected function waitForGitClient() {
    $io_channel = $this->getIOChannel();

    // If we don't wait for the client to close the connection, `git` will
    // consider it an early abort and fail. Sit around until Git is comfortable
    // that it really received all the data.
    while ($io_channel->isOpenForReading()) {
      $io_channel->update();
      $this->getErrorChannel()->flush();
      PhutilChannel::waitForAny(array($io_channel));
    }
  }

}

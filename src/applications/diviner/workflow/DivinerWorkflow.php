<?php

abstract class DivinerWorkflow extends PhutilArgumentWorkflow {

  private $atomCache;

  public function isExecutable() {
    return true;
  }

  protected function getRoot() {
    return getcwd();
  }

  protected function getConfig($key, $default = null) {
    return $default;
  }

  protected function getAtomCache() {
    if (!$this->atomCache) {
      $cache_directory = $this->getRoot().'/.divinercache';
      $this->atomCache = new DivinerAtomCache($cache_directory);
    }
    return $this->atomCache;
  }

  protected function log($message) {
    $console = PhutilConsole::getConsole();
    $console->getServer()->setEnableLog(true);
    $console->writeLog($message."\n");
  }

}

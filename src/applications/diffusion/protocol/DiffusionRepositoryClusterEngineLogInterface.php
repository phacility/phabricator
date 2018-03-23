<?php

interface DiffusionRepositoryClusterEngineLogInterface {

  public function writeClusterEngineLogMessage($message);
  public function writeClusterEngineLogProperty($key, $value);

}

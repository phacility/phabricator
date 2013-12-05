<?php

final class DrydockBlueprintScopeGuard {

  public function __construct(DrydockBlueprintImplementation $blueprint) {
    $this->blueprint = $blueprint;
  }

  public function __destruct() {
    $this->blueprint->popActiveScope();
  }

}

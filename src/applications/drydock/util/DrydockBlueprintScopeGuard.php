<?php

final class DrydockBlueprintScopeGuard extends Phobject {

  private $blueprint;

  public function __construct(DrydockBlueprintImplementation $blueprint) {
    $this->blueprint = $blueprint;
  }

  public function __destruct() {
    $this->blueprint->popActiveScope();
  }

}

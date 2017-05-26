<?php

final class NuanceItemCommandSpec
  extends Phobject {

  private $commandKey;
  private $name;
  private $icon;

  public function setCommandKey($command_key) {
    $this->commandKey = $command_key;
    return $this;
  }

  public function getCommandKey() {
    return $this->commandKey;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

}

<?php

final class DiffusionGitWireProtocolCapabilities
  extends Phobject {

  private $raw;

  public static function newFromWireFormat($raw) {
    $capabilities = new self();
    $capabilities->raw = $raw;
    return $capabilities;
  }

  public function toWireFormat() {
    return $this->raw;
  }

}

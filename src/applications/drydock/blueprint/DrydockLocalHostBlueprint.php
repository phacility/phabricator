<?php

final class DrydockLocalHostBlueprint extends DrydockBlueprint {

  public function getType() {
    return 'host';
  }

  public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    switch ($type) {
      case 'command':
        return new DrydockLocalCommandInterface();
    }

    throw new Exception("No interface of type '{$type}'.");
  }

}

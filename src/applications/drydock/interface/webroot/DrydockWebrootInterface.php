<?php

abstract class DrydockWebrootInterface extends DrydockInterface {

  final public function getInterfaceType() {
    return 'webroot';
  }

  abstract public function getURI();

}

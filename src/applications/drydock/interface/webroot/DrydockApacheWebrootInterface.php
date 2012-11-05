<?php

final class DrydockApacheWebrootInterface extends DrydockWebrootInterface {

  public function getURI() {
    return $this->getConfig('uri');
  }

}

<?php

final class ReleephDefaultUserView extends ReleephUserView {

  public function render() {
    return $this->getHandle()->renderLink();
  }

}

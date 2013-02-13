<?php

final class AphrontNullView extends AphrontView {

  public function render() {
    return $this->renderChildren();
  }

}

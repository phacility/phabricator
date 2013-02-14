<?php

final class AphrontNullView extends AphrontView {

  public function render() {
    return phutil_implode_html('', $this->renderChildren());
  }

}

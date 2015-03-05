<?php

final class DifferentialChangesetTwoUpTestRenderer
  extends DifferentialChangesetTestRenderer {

  public function isOneUpRenderer() {
    return false;
  }

  public function getRendererKey() {
    return '2up-test';
  }

}

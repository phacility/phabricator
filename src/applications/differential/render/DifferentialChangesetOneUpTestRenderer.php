<?php

final class DifferentialChangesetOneUpTestRenderer
  extends DifferentialChangesetTestRenderer {

  public function isOneUpRenderer() {
    return true;
  }

  public function getRendererKey() {
    return '1up-test';
  }

}

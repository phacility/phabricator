<?php

final class DifferentialHunkLegacy extends DifferentialHunk {

  protected $changes;

  public function getTableName() {
    return 'differential_hunk';
  }

  public function getDataEncoding() {
    return 'utf8';
  }

  public function forceEncoding($encoding) {
    // Not supported, these are always utf8.
    return $this;
  }

}

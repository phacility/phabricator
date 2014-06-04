<?php

final class DifferentialHunkLegacy extends DifferentialHunk {

  protected $changes;

  public function getTableName() {
    return 'differential_hunk';
  }

}

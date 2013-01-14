<?php

final class DifferentialChangesetOneUpRenderer
  extends DifferentialChangesetHTMLRenderer {

  public function isOneUpRenderer() {
    return true;
  }

  public function renderChangesetTable($contents) {
    throw new Exception("Not implemented!");
  }

  public function renderTextChange(
    $range_start,
    $range_len,
    $rows) {
    throw new Exception("Not implemented!");
  }

  public function renderFileChange($old_file = null,
                                   $new_file = null,
                                   $id = 0,
                                   $vs = 0) {
    throw new Exception("Not implemented!");
  }

}

<?php

abstract class DifferentialCodeWidthSensitiveView extends AphrontView {

  private $lineWidth = 80;

  private function setLineWidth($width) {
    $this->lineWidth = $width;
    return $this;
  }

  public function getLineWidth() {
    return $this->lineWidth;
  }

  public function setLineWidthFromChangesets(array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');
    if (empty($changesets)) {
      return $this;
    }

    $max = 1;
    foreach ($changesets as $changeset) {
      $max = max($max, $changeset->getWordWrapWidth());
    }
    $this->setLineWidth($max);

    return $this;
  }

  public function calculateSideBySideWidth() {
    // Width of the constant-width elements (like line numbers, padding,
    // and borders).
    $const = 148;
    $width = ceil(((1162 - $const) / 80) * $this->getLineWidth()) + $const;
    return  max(1162, $width);
  }

}

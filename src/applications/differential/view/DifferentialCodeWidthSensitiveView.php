<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

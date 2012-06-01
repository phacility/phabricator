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

final class DifferentialPrimaryPaneView extends AphrontView {

  private $lineWidth = 80;
  private $id;

  public function setLineWidth($width) {
    $this->lineWidth = $width;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
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

  public function render() {

    // This is chosen somewhat arbitrarily so the math works out correctly
    // for 80 columns and sets it to the preexisting width (1162px). It may
    // need some tweaking, but when lineWidth = 80, the computed pixel width
    // should be 1162px or something along those lines.

    // Width of the constant-width elements (like line numbers, padding,
    // and borders).
    $const = 148;
    $width = ceil(((1162 - $const) / 80) * $this->lineWidth) + $const;
    $width = max(1162, $width);

    // Override the 'td' width rule with a more specific, inline style tag.
    // TODO: move this to <head> somehow.
    $td_width = ceil((88 / 80) * $this->lineWidth);
    $style_tag = phutil_render_tag(
      'style',
      array(
        'type' => 'text/css',
      ),
      ".differential-diff td { width: {$td_width}ex; }");

    return phutil_render_tag(
      'div',
      array(
        'class' => 'differential-primary-pane',
        'style' => "max-width: {$width}px",
        'id'    => $this->id,
      ),
      $style_tag.$this->renderChildren());
  }

}

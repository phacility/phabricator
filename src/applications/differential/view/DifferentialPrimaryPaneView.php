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

final class DifferentialPrimaryPaneView
  extends DifferentialCodeWidthSensitiveView {

  private $id;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function render() {

    // This is chosen somewhat arbitrarily so the math works out correctly
    // for 80 columns and sets it to the preexisting width (1162px). It may
    // need some tweaking, but when lineWidth = 80, the computed pixel width
    // should be 1162px or something along those lines.

    // Override the 'td' width rule with a more specific, inline style tag.
    // TODO: move this to <head> somehow.
    $td_width = ceil((88 / 80) * $this->getLineWidth());
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
        'id'    => $this->id,
      ),
      $style_tag.$this->renderChildren());
  }

}

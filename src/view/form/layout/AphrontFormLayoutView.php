<?php

/*
 * Copyright 2011 Facebook, Inc.
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

/**
 * This provides the layout of an AphrontFormView without actually providing
 * the <form /> tag. Useful on its own for creating forms in other forms (like
 * dialogs) or forms which aren't submittable.
 */
final class AphrontFormLayoutView extends AphrontView {

  private $backgroundShading;
  private $padded;

  public function setBackgroundShading($shading) {
    $this->backgroundShading = $shading;
    return $this;
  }

  public function setPadded($padded) {
    $this->padded = $padded;
    return $this;
  }

  public function render() {
    $classes = array('aphront-form-view');

    if ($this->backgroundShading) {
      $classes[] = 'aphront-form-view-shaded';
    }

    if ($this->padded) {
      $classes[] = 'aphront-form-view-padded';
    }

    $classes = implode(' ', $classes);

    return phutil_render_tag(
      'div',
      array(
        'class' => $classes,
      ),
      $this->renderChildren());
  }
}

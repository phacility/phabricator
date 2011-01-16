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

final class AphrontErrorView extends AphrontView {

  private $title;
  private $errors;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setErrors(array $errors) {
    $this->errors = $errors;
    return $this;
  }

  final public function render() {

    $errors = $this->errors;
    if ($errors) {
      $list = array();
      foreach ($errors as $error) {
        $list[] = phutil_render_tag(
          'li',
          array(),
          phutil_escape_html($error));
      }
      $list = '<ul>'.implode("\n", $list).'</ul>';
    } else {
      $list = null;
    }

    $title = $this->title;
    if (strlen($title)) {
      $title = '<h1>'.phutil_escape_html($title).'</h1>';
    } else {
      $title = null;
    }

    return
      '<div class="aphront-error-view">'.
        $title.
        $list.
      '</div>';

  }
}

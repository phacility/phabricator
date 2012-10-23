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

final class AphrontErrorView extends AphrontView {

  const SEVERITY_ERROR = 'error';
  const SEVERITY_WARNING = 'warning';
  const SEVERITY_NOTICE = 'notice';
  const SEVERITY_NODATA = 'nodata';

  private $title;
  private $errors;
  private $severity;
  private $id;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setSeverity($severity) {
    $this->severity = $severity;
    return $this;
  }

  public function setErrors(array $errors) {
    $this->errors = $errors;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  final public function render() {

    require_celerity_resource('aphront-error-view-css');

    $errors = $this->errors;
    if ($errors) {
      $list = array();
      foreach ($errors as $error) {
        $list[] = phutil_render_tag(
          'li',
          array(),
          phutil_escape_html($error));
      }
      $list = phutil_render_tag(
        'ul',
        array(
          'class' => 'aphront-error-view-list',
        ),
        implode("\n", $list));
    } else {
      $list = null;
    }

    $title = $this->title;
    if (strlen($title)) {
      $title = phutil_render_tag(
        'h1',
        array(
          'class' => 'aphront-error-view-head',
        ),
        phutil_escape_html($title));
    } else {
      $title = null;
    }

    $this->severity = nonempty($this->severity, self::SEVERITY_ERROR);

    $more_classes = array();
    $more_classes[] = 'aphront-error-severity-'.$this->severity;
    $more_classes = implode(' ', $more_classes);

    return phutil_render_tag(
      'div',
      array(
        'id' => $this->id,
        'class' => 'aphront-error-view '.$more_classes,
      ),
      $title.
      phutil_render_tag(
        'div',
        array(
          'class' => 'aphront-error-view-body',
        ),
        $this->renderChildren().
        $list));
  }
}

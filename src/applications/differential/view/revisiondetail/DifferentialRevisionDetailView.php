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

final class DifferentialRevisionDetailView extends AphrontView {

  private $revision;
  private $properties;
  private $actions;

  public function setRevision($revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setProperties(array $properties) {
    $this->properties = $properties;
    return $this;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function render() {

    require_celerity_resource('differential-core-view-css');
    require_celerity_resource('differential-revision-detail-css');

    $revision = $this->revision;

    $rows = array();
    foreach ($this->properties as $key => $field) {
      $rows[] =
        '<tr>'.
          '<th>'.phutil_escape_html($key).':</th>'.
          '<td>'.$field.'</td>'.
        '</tr>';
    }

    $properties =
      '<table class="differential-revision-properties">'.
        implode("\n", $rows).
      '</table>';

    $actions = array();
    foreach ($this->actions as $action) {
      if (empty($action['href'])) {
        $tag = 'span';
      } else {
        $tag = 'a';
      }
      $actions[] = phutil_render_tag(
        $tag,
        array(
          'href'  => idx($action, 'href'),
          'class' => idx($action, 'class'),
        ),
        phutil_escape_html($action['name']));
    }
    $actions = implode("\n", $actions);

    return
      '<div class="differential-revision-detail differential-panel">'.
        '<div class="differential-revision-actions">'.
          $actions.
        '</div>'.
        '<div class="differential-revision-detail-core">'.
          '<h1>'.phutil_escape_html($revision->getTitle()).'</h1>'.
          $properties.
        '</div>'.
        '<div style="clear: both;"></div>'.
      '</div>';
  }
}

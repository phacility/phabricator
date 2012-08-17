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

final class PhabricatorSourceCodeView extends AphrontView {

  private $lines;

  public function setLines(array $lines) {
    $this->lines = $lines;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-source-code-view-css');
    require_celerity_resource('syntax-highlighting-css');

    Javelin::initBehavior('phabricator-oncopy', array());

    $line_number = 1;

    $rows = array();
    foreach ($this->lines as $line) {

      // TODO: Provide nice links.

      $rows[] =
        '<tr>'.
          '<th class="phabricator-source-line">'.
            phutil_escape_html($line_number).
          '</th>'.
          '<td class="phabricator-source-code">'.
            "\xE2\x80\x8B".
            $line.
          '</td>'.
        '</tr>';

      $line_number++;
    }

    $classes = array();
    $classes[] = 'phabricator-source-code-view';
    $classes[] = 'remarkup-code';
    $classes[] = 'PhabricatorMonospaced';

    return phutil_render_tag(
      'table',
      array(
        'class' => implode(' ', $classes),
      ),
      implode('', $rows));
  }

}

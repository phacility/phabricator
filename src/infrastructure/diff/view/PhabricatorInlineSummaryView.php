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

final class PhabricatorInlineSummaryView extends AphrontView {

  private $groups = array();

  public function addCommentGroup($name, array $items) {
    if (!isset($this->groups[$name])) {
      $this->groups[$name] = $items;
    } else {
      $this->groups[$name] = array_merge($this->groups[$name], $items);
    }
    return $this;
  }

  public function render() {
    require_celerity_resource('inline-comment-summary-css');
    return $this->renderHeader().$this->renderTable();
  }

  private function renderHeader() {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-inline-summary',
      ),
      'Inline Comments');
  }

  private function renderTable() {
    $rows = array();
    foreach ($this->groups as $group => $items) {

      $has_where = false;
      foreach ($items as $item) {
        if (!empty($item['where'])) {
          $has_where = true;
          break;
        }
      }

      $rows[] =
        '<tr>'.
          '<th colspan="3">'.
            phutil_escape_html($group).
          '</th>'.
        '</tr>';

      foreach ($items as $item) {

        $items = isort($items, 'line');

        $line = $item['line'];
        $length = $item['length'];
        if ($length) {
          $lines = $line."\xE2\x80\x93".($line + $length);
        } else {
          $lines = $line;
        }

        if (isset($item['href'])) {
          $href = $item['href'];
          $target = '_blank';
          $tail = " \xE2\x86\x97";
        } else {
          $href = '#inline-'.$item['id'];
          $target = null;
          $tail = null;
        }

        $lines = phutil_escape_html($lines);
        if ($href) {
          $lines = phutil_render_tag(
            'a',
            array(
              'href'    => $href,
              'target'  => $target,
              'class'   => 'num',
            ),
            $lines.$tail);
        }

        $where = idx($item, 'where');

        $colspan = ($has_where ? '' : ' colspan="2"');
        $rows[] =
          '<tr>'.
            '<td class="inline-line-number">'.$lines.'</td>'.
            ($has_where ?
              '<td class="inline-which-diff">'.
                phutil_escape_html($where).
              '</td>'
              : null).
            '<td class="inline-summary-content"'.$colspan.'>'.
              '<div class="phabricator-remarkup">'.
                $item['content'].
              '</div>'.
            '</td>'.
          '</tr>';
      }
    }

    return phutil_render_tag(
      'table',
      array(
        'class' => 'phabricator-inline-summary-table',
      ),
      implode("\n", $rows));
  }

}

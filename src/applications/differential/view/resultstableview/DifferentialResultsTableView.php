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

final class DifferentialResultsTableView extends AphrontView {

  private $rows;
  private $showMoreString;

  public function setRows(array $rows) {
    $this->rows = $rows;
    return $this;
  }

  public function setShowMoreString($show_more_string) {
    $this->showMoreString = $show_more_string;
    return $this;
  }

  public function render() {

    $rows = array();

    $any_hidden = false;
    foreach ($this->rows as $row) {

      $style = idx($row, 'style');
      switch ($style) {
        case 'section':
          $cells = phutil_render_tag(
            'th',
            array(
              'colspan' => 2,
            ),
            idx($row, 'name'));
          break;
        default:
          $name = phutil_render_tag(
            'th',
            array(
            ),
            idx($row, 'name'));
          $value = phutil_render_tag(
            'td',
            array(
            ),
            idx($row, 'value'));
          $cells = $name.$value;
          break;
      }

      $show = idx($row, 'show');

      $rows[] = javelin_render_tag(
        'tr',
        array(
          'style' => $show ? null : 'display: none',
          'sigil' => $show ? null : 'differential-results-row-toggle',
          'class' => 'differential-results-row-'.$style,
        ),
        $cells);

      if (!$show) {
        $any_hidden = true;
      }
    }

    if ($any_hidden) {
      $show_more = javelin_render_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
        ),
        $this->showMoreString);

      $hide_more = javelin_render_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
        ),
        'Hide');

      $rows[] = javelin_render_tag(
        'tr',
        array(
          'class' => 'differential-results-row-show',
          'sigil' => 'differential-results-row-show',
        ),
        '<th colspan="2">'.$show_more.'</td>');

      $rows[] = javelin_render_tag(
        'tr',
        array(
          'class' => 'differential-results-row-show',
          'sigil' => 'differential-results-row-hide',
          'style' => 'display: none',
        ),
        '<th colspan="2">'.$hide_more.'</th>');

      Javelin::initBehavior('differential-show-field-details');
    }

    require_celerity_resource('differential-results-table-css');

    return javelin_render_tag(
      'table',
      array(
        'class' => 'differential-results-table',
        'sigil' => 'differential-results-table',
      ),
      implode("\n", $rows));
  }


}

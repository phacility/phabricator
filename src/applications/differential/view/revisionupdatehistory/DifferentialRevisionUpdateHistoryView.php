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

final class DifferentialRevisionUpdateHistoryView extends AphrontView {

  private $diffs = array();

  public function setDiffs($diffs) {
    $this->diffs = $diffs;
    return $this;
  }

  public function render() {

    require_celerity_resource('differential-core-view-css');
    require_celerity_resource('differential-revision-history-css');

    $data = array(
      array(
        'name' => 'Base',
        'id'   => null,
        'desc' => 'Base',
        'old'  => false,
        'new'  => false,
        'age'  => null,
        'lint' => null,
        'unit' => null,
      ),
    );

    $seq = 0;
    foreach ($this->diffs as $diff) {
      $data[] = array(
        'name' => 'Diff '.(++$seq),
        'id'   => $diff->getID(),
        'desc' => 'TODO',//$diff->getDescription(),
        'old'  => false,
        'new'  => false,
        'age'  => $diff->getDateCreated(),
        'lint' => $diff->getLintStatus(),
        'unit' => $diff->getUnitStatus(),
      );
    }

    $idx = 0;
    $rows = array();
    foreach ($data as $row) {

      $name = phutil_escape_html($row['name']);
      $id   = phutil_escape_html($row['id']);

      $lint = '*';
      $unit = '*';
      $old = '<input type="radio" name="old" />';
      $new = '<input type="radio" name="new" />';

      $desc = 'TODO';
      $age = '-';

      if (++$idx % 2) {
        $class = ' class="alt"';
      } else {
        $class = null;
      }

      $rows[] =
        '<tr'.$class.'>'.
          '<td class="revhistory-name">'.$name.'</td>'.
          '<td class="revhistory-id">'.$id.'</td>'.
          '<td class="revhistory-desc">'.$desc.'</td>'.
          '<td class="revhistory-age">'.$age.'</td>'.
          '<td class="revhistory-star">'.$lint.'</td>'.
          '<td class="revhistory-star">'.$unit.'</td>'.
          '<td class="revhistory-old">'.$old.'</td>'.
          '<td class="revhistory-new">'.$new.'</td>'.
        '</tr>';
    }

    $select = '<select><option>Ignore All</option></select>';

    return
      '<div class="differential-revision-history differential-panel">'.
        '<h1>Revision Update History</h1>'.
        '<form>'.
          '<table class="differential-revision-history-table">'.
            '<tr>'.
              '<th>Diff</th>'.
              '<th>ID</th>'.
              '<th>Description</th>'.
              '<th>Age</th>'.
              '<th>Lint</th>'.
              '<th>Unit</th>'.
            '</tr>'.
            implode("\n", $rows).
            '<tr>'.
              '<td colspan="8" class="diff-differ-submit">'.
                '<label>Whitespace Changes: '.$select.'</label>'.
                '<button class="disabled"
                  disabled="disabled">Show Diff</button>'.
              '</td>'.
            '</tr>'.
          '</table>'.
        '</form>'.
      '</div>';
  }
}

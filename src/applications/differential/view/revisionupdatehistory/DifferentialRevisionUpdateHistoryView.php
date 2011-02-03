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
  private $selectedVersusDiffID;
  private $selectedDiffID;

  public function setDiffs($diffs) {
    $this->diffs = $diffs;
    return $this;
  }

  public function setSelectedVersusDiffID($id) {
    $this->selectedVersusDiffID = $id;
    return $this;
  }

  public function setSelectedDiffID($id) {
    $this->selectedDiffID = $id;
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
        'age'  => $diff->getDateCreated(),
        'lint' => $diff->getLintStatus(),
        'unit' => $diff->getUnitStatus(),
      );
    }

    $max_id = $diff->getID();

    $idx = 0;
    $rows = array();
    foreach ($data as $row) {

      $name = phutil_escape_html($row['name']);
      $id   = phutil_escape_html($row['id']);

      $radios = array();
      $lint = '*';
      $unit = '*';

      $old_class = null;
      $new_class = null;

      if ($max_id != $id) {
        $uniq = celerity_generate_unique_node_id();
        $old_checked = ($this->selectedVersusDiffID == $id);
        $old = phutil_render_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'vs',
            'value' => '0',
            'id' => $uniq,
            'checked' => $old_checked ? 'checked' : null,
          ));
        $radios[] = $uniq;
        if ($old_checked) {
          $old_class = " revhistory-old-now";
        }
      } else {
        $old = null;
      }

      if ($id) {
        $new_checked = ($this->selectedDiffID == $id);
        $new = phutil_render_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'id',
            'value' => $id,
            'checked' => $new_checked ? 'checked' : null,
          ));
        if ($new_checked) {
          $new_class = " revhistory-new-now";
        }
      } else {
        $new = null;
      }

      Javelin::initBehavior(
        'differential-diff-radios',
        array(
          'radios' => $radios,
        ));


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
          '<td class="revhistory-old'.$old_class.'">'.$old.'</td>'.
          '<td class="revhistory-new'.$new_class.'">'.$new.'</td>'.
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
                '<button>Show Diff</button>'.
              '</td>'.
            '</tr>'.
          '</table>'.
        '</form>'.
      '</div>';
  }
}

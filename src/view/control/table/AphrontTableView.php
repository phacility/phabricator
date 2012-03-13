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

final class AphrontTableView extends AphrontView {

  protected $data;
  protected $headers;
  protected $rowClasses = array();
  protected $columnClasses = array();
  protected $zebraStripes = true;
  protected $noDataString;
  protected $className;
  protected $columnVisibility = array();

  public function __construct(array $data) {
    $this->data = $data;
  }

  public function setHeaders(array $headers) {
    $this->headers = $headers;
    return $this;
  }

  public function setColumnClasses(array $column_classes) {
    $this->columnClasses = $column_classes;
    return $this;
  }

  public function setRowClasses(array $row_classes) {
    $this->rowClasses = $row_classes;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setClassName($class_name) {
    $this->className = $class_name;
    return $this;
  }

  public function setZebraStripes($zebra_stripes) {
    $this->zebraStripes = $zebra_stripes;
    return $this;
  }

  public function setColumnVisibility(array $visibility) {
    $this->columnVisibility = $visibility;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-table-view-css');

    $class = $this->className;
    if ($class !== null) {
      $class = ' class="aphront-table-view '.$class.'"';
    } else {
      $class = ' class="aphront-table-view"';
    }
    $table = array('<table'.$class.'>');

    $col_classes = array();
    foreach ($this->columnClasses as $key => $class) {
      if (strlen($class)) {
        $col_classes[] = ' class="'.$class.'"';
      } else {
        $col_classes[] = null;
      }
    }

    $visibility = array_values($this->columnVisibility);
    $headers = $this->headers;
    if ($headers) {
      while (count($headers) > count($visibility)) {
        $visibility[] = true;
      }
      $table[] = '<tr>';
      foreach ($headers as $col_num => $header) {
        if (!$visibility[$col_num]) {
          continue;
        }
        $class = idx($col_classes, $col_num);
        $table[] = '<th'.$class.'>'.$header.'</th>';
      }
      $table[] = '</tr>';
    }

    $data = $this->data;
    if ($data) {
      $row_num = 0;
      foreach ($data as $row) {
        while (count($row) > count($col_classes)) {
          $col_classes[] = null;
        }
        while (count($row) > count($visibility)) {
          $visibility[] = true;
        }
        $class = idx($this->rowClasses, $row_num);
        if ($this->zebraStripes && ($row_num % 2)) {
          if ($class !== null) {
            $class = 'alt alt-'.$class;
          } else {
            $class = 'alt';
          }
        }
        if ($class !== null) {
          $class = ' class="'.$class.'"';
        }
        $table[] = '<tr'.$class.'>';
        // NOTE: Use of a separate column counter is to allow this to work
        // correctly if the row data has string or non-sequential keys.
        $col_num = 0;
        foreach ($row as $value) {
          if (!$visibility[$col_num]) {
            ++$col_num;
            continue;
          }
          $class = $col_classes[$col_num];
          if ($class !== null) {
            $table[] = '<td'.$class.'>';
          } else {
            $table[] = '<td>';
          }
          $table[] = $value.'</td>';
          ++$col_num;
        }
        ++$row_num;
      }
    } else {
      $colspan = max(count($headers), 1);
      $table[] =
        '<tr class="no-data"><td colspan="'.$colspan.'">'.
          coalesce($this->noDataString, 'No data available.').
        '</td></tr>';
    }
    $table[] = '</table>';
    return implode('', $table);
  }

  public static function renderSingleDisplayLine($line) {

    // TODO: Is there a cleaner way to do this? We use a relative div with
    // overflow hidden to provide the bounds, and an absolute span with
    // white-space: pre to prevent wrapping. We need to append a character
    // (&nbsp; -- nonbreaking space) afterward to give the bounds div height
    // (alternatively, we could hard-code the line height). This is gross but
    // it's not clear that there's a better appraoch.

    return phutil_render_tag(
      'div',
      array(
        'class' => 'single-display-line-bounds',
      ),
      phutil_render_tag(
        'span',
        array(
          'class' => 'single-display-line-content',
        ),
        $line).'&nbsp;');
  }


}


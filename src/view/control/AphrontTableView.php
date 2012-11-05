<?php

final class AphrontTableView extends AphrontView {

  protected $data;
  protected $headers;
  protected $rowClasses = array();
  protected $columnClasses = array();
  protected $cellClasses = array();
  protected $zebraStripes = true;
  protected $noDataString;
  protected $className;
  protected $columnVisibility = array();

  protected $sortURI;
  protected $sortParam;
  protected $sortSelected;
  protected $sortReverse;
  protected $sortValues;

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

  public function setCellClasses(array $cell_classes) {
    $this->cellClasses = $cell_classes;
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

  /**
   * Parse a sorting parameter:
   *
   *   list($sort, $reverse) = AphrontTableView::parseSortParam($sort_param);
   *
   * @param string  Sort request parameter.
   * @return pair   Sort value, sort direction.
   */
  public static function parseSort($sort) {
    return array(ltrim($sort, '-'), preg_match('/^-/', $sort));
  }

  public function makeSortable(
    PhutilURI $base_uri,
    $param,
    $selected,
    $reverse,
    array $sort_values) {

    $this->sortURI        = $base_uri;
    $this->sortParam      = $param;
    $this->sortSelected   = $selected;
    $this->sortReverse    = $reverse;
    $this->sortValues     = array_values($sort_values);

    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-table-view-css');

    $table_class = $this->className;
    if ($table_class !== null) {
      $table_class = ' class="aphront-table-view '.$table_class.'"';
    } else {
      $table_class = ' class="aphront-table-view"';
    }
    $table = array('<table'.$table_class.'>');

    $col_classes = array();
    foreach ($this->columnClasses as $key => $class) {
      if (strlen($class)) {
        $col_classes[] = $class;
      } else {
        $col_classes[] = null;
      }
    }

    $visibility = array_values($this->columnVisibility);
    $headers = $this->headers;
    $sort_values = $this->sortValues;
    if ($headers) {
      while (count($headers) > count($visibility)) {
        $visibility[] = true;
      }
      while (count($headers) > count($sort_values)) {
        $sort_values[] = null;
      }
      $table[] = '<tr>';
      foreach ($headers as $col_num => $header) {
        if (!$visibility[$col_num]) {
          continue;
        }

        $classes = array();

        if (!empty($col_classes[$col_num])) {
          $classes[] = $col_classes[$col_num];
        }

        if ($sort_values[$col_num] !== null) {
          $classes[] = 'aphront-table-view-sortable';

          $sort_value = $sort_values[$col_num];
          $sort_glyph = "\xE2\x86\x93";
          if ($sort_value == $this->sortSelected) {
            if ($this->sortReverse) {
              $sort_glyph = "\xE2\x86\x91";
            } else if (!$this->sortReverse) {
              $sort_value = '-'.$sort_value;
            }
            $classes[] = 'aphront-table-view-sortable-selected';
          }

          $sort_glyph = phutil_render_tag(
            'span',
            array(
              'class' => 'aphront-table-view-sort-glyph',
            ),
            $sort_glyph);

          $header = phutil_render_tag(
            'a',
            array(
              'href'  => $this->sortURI->alter($this->sortParam, $sort_value),
              'class' => 'aphront-table-view-sort-link',
            ),
            $sort_glyph.' '.$header);
        }

        if ($classes) {
          $class = ' class="'.implode(' ', $classes).'"';
        } else {
          $class = null;
        }

        $table[] = '<th'.$class.'>'.$header.'</th>';
      }
      $table[] = '</tr>';
    }

    foreach ($col_classes as $key => $value) {

      if (($sort_values[$key] !== null) &&
          ($sort_values[$key] == $this->sortSelected)) {
        $value = trim($value.' sorted-column');
      }

      if ($value !== null) {
        $col_classes[$key] = $value;
      }
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
          if (!empty($this->cellClasses[$row_num][$col_num])) {
            $class = trim($class.' '.$this->cellClasses[$row_num][$col_num]);
          }
          if ($class !== null) {
            $table[] = '<td class="'.$class.'">';
          } else {
            $table[] = '<td>';
          }
          $table[] = $value.'</td>';
          ++$col_num;
        }
        ++$row_num;
      }
    } else {
      $colspan = max(count(array_filter($visibility)), 1);
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


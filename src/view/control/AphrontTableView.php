<?php

final class AphrontTableView extends AphrontView {

  protected $data;
  protected $headers;
  protected $shortHeaders = array();
  protected $rowClasses = array();
  protected $columnClasses = array();
  protected $cellClasses = array();
  protected $zebraStripes = true;
  protected $noDataString;
  protected $className;
  protected $notice;
  protected $columnVisibility = array();
  private $deviceVisibility = array();

  private $columnWidths = array();

  protected $sortURI;
  protected $sortParam;
  protected $sortSelected;
  protected $sortReverse;
  protected $sortValues = array();
  private $deviceReadyTable;

  private $rowDividers = array();

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

  public function setColumnWidths(array $widths) {
    $this->columnWidths = $widths;
    return $this;
  }

  public function setRowDividers(array $dividers) {
    $this->rowDividers = $dividers;
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

  public function setNotice($notice) {
    $this->notice = $notice;
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

  public function setDeviceVisibility(array $device_visibility) {
    $this->deviceVisibility = $device_visibility;
    return $this;
  }

  public function setDeviceReadyTable($ready) {
    $this->deviceReadyTable = $ready;
    return $this;
  }

  public function setShortHeaders(array $short_headers) {
    $this->shortHeaders = $short_headers;
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

    $table = array();

    $col_classes = array();
    foreach ($this->columnClasses as $key => $class) {
      if ($class !== null && strlen($class)) {
        $col_classes[] = $class;
      } else {
        $col_classes[] = null;
      }
    }

    $visibility = array_values($this->columnVisibility);
    $device_visibility = array_values($this->deviceVisibility);

    $column_widths = $this->columnWidths;

    $headers = $this->headers;
    $short_headers = $this->shortHeaders;
    $sort_values = $this->sortValues;
    if ($headers) {
      while (count($headers) > count($visibility)) {
        $visibility[] = true;
      }
      while (count($headers) > count($device_visibility)) {
        $device_visibility[] = true;
      }
      while (count($headers) > count($short_headers)) {
        $short_headers[] = null;
      }
      while (count($headers) > count($sort_values)) {
        $sort_values[] = null;
      }

      $tr = array();
      foreach ($headers as $col_num => $header) {
        if (!$visibility[$col_num]) {
          continue;
        }

        $classes = array();

        if (!empty($col_classes[$col_num])) {
          $classes[] = $col_classes[$col_num];
        }

        if (empty($device_visibility[$col_num])) {
          $classes[] = 'aphront-table-view-nodevice';
        }

        if ($sort_values[$col_num] !== null) {
          $classes[] = 'aphront-table-view-sortable';

          $sort_value = $sort_values[$col_num];
          $sort_glyph_class = 'aphront-table-down-sort';
          if ($sort_value == $this->sortSelected) {
            if ($this->sortReverse) {
              $sort_glyph_class = 'aphront-table-up-sort';
            } else if (!$this->sortReverse) {
              $sort_value = '-'.$sort_value;
            }
            $classes[] = 'aphront-table-view-sortable-selected';
          }

          $sort_glyph = phutil_tag(
            'span',
            array(
              'class' => $sort_glyph_class,
            ),
            '');

          $header = phutil_tag(
            'a',
            array(
              'href'  => $this->sortURI->alter($this->sortParam, $sort_value),
              'class' => 'aphront-table-view-sort-link',
            ),
            array(
              $header,
              ' ',
              $sort_glyph,
            ));
        }

        if ($classes) {
          $class = implode(' ', $classes);
        } else {
          $class = null;
        }

        if ($short_headers[$col_num] !== null) {
          $header_nodevice = phutil_tag(
            'span',
            array(
              'class' => 'aphront-table-view-nodevice',
            ),
            $header);
          $header_device = phutil_tag(
            'span',
            array(
              'class' => 'aphront-table-view-device',
            ),
            $short_headers[$col_num]);

          $header = hsprintf('%s %s', $header_nodevice, $header_device);
        }

        $style = null;
        if (isset($column_widths[$col_num])) {
          $style = 'width: '.$column_widths[$col_num].';';
        }

        $tr[] = phutil_tag(
          'th',
          array(
            'class' => $class,
            'style' => $style,
          ),
          $header);
      }
      $table[] = phutil_tag('tr', array(), $tr);
    }

    foreach ($col_classes as $key => $value) {

      if (isset($sort_values[$key]) &&
          ($sort_values[$key] == $this->sortSelected)) {
        $value = trim($value.' sorted-column');
      }

      if ($value !== null) {
        $col_classes[$key] = $value;
      }
    }

    $dividers = $this->rowDividers;

    $data = $this->data;
    if ($data) {
      $row_num = 0;
      $row_idx = 0;
      foreach ($data as $row) {
        $is_divider = !empty($dividers[$row_num]);

        $row_size = count($row);
        while (count($row) > count($col_classes)) {
          $col_classes[] = null;
        }
        while (count($row) > count($visibility)) {
          $visibility[] = true;
        }
        while (count($row) > count($device_visibility)) {
          $device_visibility[] = true;
        }
        $tr = array();
        // NOTE: Use of a separate column counter is to allow this to work
        // correctly if the row data has string or non-sequential keys.
        $col_num = 0;
        foreach ($row as $value) {
          if (!$visibility[$col_num]) {
            ++$col_num;
            continue;
          }
          $class = $col_classes[$col_num];
          if (empty($device_visibility[$col_num])) {
            $class = trim($class.' aphront-table-view-nodevice');
          }
          if (!empty($this->cellClasses[$row_num][$col_num])) {
            $class = trim($class.' '.$this->cellClasses[$row_num][$col_num]);
          }

          if ($is_divider) {
            $tr[] = phutil_tag(
              'td',
              array(
                'class' => 'row-divider',
                'colspan' => count($visibility),
              ),
              $value);
            $row_idx = -1;
            break;
          }

          $tr[] = phutil_tag(
            'td',
            array(
              'class' => $class,
            ),
            $value);
          ++$col_num;
        }

        $class = idx($this->rowClasses, $row_num);
        if ($this->zebraStripes && ($row_idx % 2)) {
          if ($class !== null) {
            $class = 'alt alt-'.$class;
          } else {
            $class = 'alt';
          }
        }

        $table[] = phutil_tag('tr', array('class' => $class), $tr);
        ++$row_num;
        ++$row_idx;
      }
    } else {
      $colspan = max(count(array_filter($visibility)), 1);
      $table[] = phutil_tag(
        'tr',
        array('class' => 'no-data'),
        phutil_tag(
          'td',
          array('colspan' => $colspan),
          coalesce($this->noDataString, pht('No data available.'))));
    }

    $classes = array();
    $classes[] = 'aphront-table-view';
    if ($this->className !== null) {
      $classes[] = $this->className;
    }

    if ($this->deviceReadyTable) {
      $classes[] = 'aphront-table-view-device-ready';
    }

    if ($this->columnWidths) {
      $classes[] = 'aphront-table-view-fixed';
    }

    $notice = null;
    if ($this->notice) {
      $notice = phutil_tag(
        'div',
        array(
          'class' => 'aphront-table-notice',
        ),
        $this->notice);
    }

    $html = phutil_tag(
      'table',
      array(
        'class' => implode(' ', $classes),
      ),
      $table);

    return phutil_tag_div(
      'aphront-table-wrap',
      array(
        $notice,
        $html,
      ));
  }

  public static function renderSingleDisplayLine($line) {

    // TODO: Is there a cleaner way to do this? We use a relative div with
    // overflow hidden to provide the bounds, and an absolute span with
    // white-space: pre to prevent wrapping. We need to append a character
    // (&nbsp; -- nonbreaking space) afterward to give the bounds div height
    // (alternatively, we could hard-code the line height). This is gross but
    // it's not clear that there's a better appraoch.

    return phutil_tag(
      'div',
      array(
        'class' => 'single-display-line-bounds',
      ),
      array(
        phutil_tag(
          'span',
          array(
            'class' => 'single-display-line-content',
          ),
          $line),
        "\xC2\xA0",
      ));
  }


}

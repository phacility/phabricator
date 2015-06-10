<?php

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
          $cells = phutil_tag(
            'th',
            array(
              'colspan' => 2,
            ),
            idx($row, 'name'));
          break;
        default:
          $name = phutil_tag(
            'th',
            array(
            ),
            idx($row, 'name'));
          $value = phutil_tag(
            'td',
            array(
            ),
            idx($row, 'value'));
          $cells = array($name, $value);
          break;
      }

      $show = idx($row, 'show');

      $rows[] = javelin_tag(
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
      $show_more = javelin_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
        ),
        $this->showMoreString);

      $hide_more = javelin_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
        ),
        pht('Hide'));

      $rows[] = javelin_tag(
        'tr',
        array(
          'class' => 'differential-results-row-show',
          'sigil' => 'differential-results-row-show',
        ),
        phutil_tag('th', array('colspan' => 2), $show_more));

      $rows[] = javelin_tag(
        'tr',
        array(
          'class' => 'differential-results-row-show',
          'sigil' => 'differential-results-row-hide',
          'style' => 'display: none',
        ),
        phutil_tag('th', array('colspan' => 2), $hide_more));

      $this->initBehavior('differential-show-field-details');
    }

    $this->requireResource('differential-results-table-css');

    return javelin_tag(
      'table',
      array(
        'class' => 'differential-results-table',
        'sigil' => 'differential-results-table',
      ),
      $rows);
  }


}

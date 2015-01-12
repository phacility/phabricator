<?php

final class PHUIInfoPanelView extends AphrontView {

  private $header;
  private $progress = null;
  private $columns = 3;
  private $infoblock = array();

  protected function canAppendChild() {
    return false;
  }

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setProgress($progress) {
    $this->progress = $progress;
    return $this;
  }

  public function setColumns($columns) {
    $this->columns = $columns;
    return $this;
  }

  public function addInfoblock($num, $text) {
    $this->infoblock[] = array($num, $text);
    return $this;
  }

  public function render() {
    require_celerity_resource('phui-info-panel-css');

    $trs = array();
    $rows = ceil(count($this->infoblock) / $this->columns);
    for ($i = 0; $i < $rows; $i++) {
      $tds = array();
      $ii = 1;
      foreach ($this->infoblock as $key => $cell) {
        $tds[] = $this->renderCell($cell);
        unset($this->infoblock[$key]);
        $ii++;
        if ($ii > $this->columns) {
          break;
        }
      }
      $trs[] = phutil_tag(
        'tr',
        array(
          'class' => 'phui-info-panel-table-row',
        ),
        $tds);
    }

    $table = phutil_tag(
      'table',
      array(
        'class' => 'phui-info-panel-table',
      ),
      $trs);

    $table = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_MEDIUM)
      ->appendChild($table);

    $progress = null;
    if ($this->progress) {
      $progress = phutil_tag(
        'div',
        array(
          'class' => 'phui-info-panel-progress',
          'style' => 'width: '.(int)$this->progress.'%;',
        ),
        null);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($this->header)
      ->appendChild($table)
      ->appendChild($progress);

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-info-panel',
      ),
      $box);
  }

  private function renderCell($cell) {
    $number = phutil_tag(
      'div',
      array(
        'class' => 'phui-info-panel-number',
      ),
      $cell[0]);

    $text = phutil_tag(
      'div',
      array(
        'class' => 'phui-info-panel-text',
      ),
      $cell[1]);

    return phutil_tag(
      'td',
      array(
        'class' => 'phui-info-panel-table-cell',
        'align' => 'center',
        'width' => floor(100 / $this->columns).'%',
      ),
      array(
        $number,
        $text,
      ));
  }
}

<?php

final class DivinerReturnTableView extends AphrontTagView {

  private $return;
  private $header;

  public function setReturn(array $return) {
    $this->return = $return;
    return $this;
  }

  public function setHeader($text) {
    $this->header = $text;
    return $this;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'diviner-table-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('diviner-shared-css');

    $return = $this->return;

    $type = idx($return, 'doctype');
    if (!$type) {
      $type = idx($return, 'type');
    }

    $docs = idx($return, 'docs');

    $cells = array();

    $cells[] = phutil_tag(
      'td',
      array(
        'class' => 'diviner-return-table-type diviner-monospace',
      ),
      $type);

    $cells[] = phutil_tag(
      'td',
      array(
        'class' => 'diviner-return-table-docs',
      ),
      $docs);

    $rows = phutil_tag(
      'tr',
      array(),
      $cells);

    $table = phutil_tag(
      'table',
      array(
        'class' => 'diviner-return-table-view',
      ),
      $rows);

    $header = phutil_tag(
      'span',
      array(
        'class' => 'diviner-table-header',
      ),
      $this->header);

    return array($header, $table);
  }

}

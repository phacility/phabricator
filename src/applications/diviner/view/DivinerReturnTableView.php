<?php

final class DivinerReturnTableView extends AphrontTagView {

  private $return;

  public function setReturn(array $return) {
    $this->return = $return;
    return $this;
  }

  public function getTagName() {
    return 'table';
  }

  public function getTagAttributes() {
    return array(
      'class' => 'diviner-return-table-view',
    );
  }

  public function getTagContent() {
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

    return phutil_tag('tr', array(), $cells);
  }

}

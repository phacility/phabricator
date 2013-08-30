<?php

final class DivinerParameterTableView extends AphrontTagView {

  private $parameters;

  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
    return $this;
  }

  public function getTagName() {
    return 'table';
  }

  public function getTagAttributes() {
    return array(
      'class' => 'diviner-parameter-table-view',
    );
  }

  public function getTagContent() {
    require_celerity_resource('diviner-shared-css');

    $rows = array();
    foreach ($this->parameters as $param) {
      $cells = array();

      $type = idx($param, 'doctype');
      if (!$type) {
        $type = idx($param, 'type');
      }

      $name = idx($param, 'name');
      $docs = idx($param, 'docs');

      $cells[] = phutil_tag(
        'td',
        array(
          'class' => 'diviner-parameter-table-type diviner-monospace',
        ),
        $type);

      $cells[] = phutil_tag(
        'td',
        array(
          'class' => 'diviner-parameter-table-name diviner-monospace',
        ),
        $name);

      $cells[] = phutil_tag(
        'td',
        array(
          'class' => 'diviner-parameter-table-docs',
        ),
        $docs);

      $rows[] = phutil_tag('tr', array(), $cells);
    }

    return $rows;
  }

}

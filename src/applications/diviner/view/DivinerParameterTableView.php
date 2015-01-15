<?php

final class DivinerParameterTableView extends AphrontTagView {

  private $parameters;
  private $header;

  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
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

    $table = phutil_tag(
      'table',
      array(
        'class' => 'diviner-parameter-table-view',
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

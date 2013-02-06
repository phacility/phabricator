<?php

final class PhabricatorTypeaheadManiphestCustomAttributeDatasourceController
  extends PhabricatorTypeaheadDatasourceController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = base64_decode($data['key']);
  }

  public function processRequest() {

    $request = $this->getRequest();
    $query = $request->getStr('q');

    $extensions = ManiphestTaskExtensions::newExtensions();
    $aux_fields = $extensions->getAuxiliaryFieldSpecifications();

    $results = array();

    foreach ($aux_fields as $aux_field) {
      if ($aux_field->getAuxiliaryKey() == $this->key) {
        if ($aux_field->getFieldType() == ManiphestAuxiliaryFieldDefaultSpecification::TYPE_SELECT) {
          foreach ($aux_field->getSelectOptions() as $key => $value) {
            $results[] = id(new PhabricatorTypeaheadResult())
              ->setName($value)
              ->setPHID($key);
          }
        }
      }
    }

    $content = mpull($results, 'getWireFormat');

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($content);
    }

    // If there's a non-Ajax request to this endpoint, show results in a tabular
    // format to make it easier to debug typeahead output.

    $rows = array();
    foreach ($results as $result) {
      $wire = $result->getWireFormat();
      foreach ($wire as $k => $v) {
        $wire[$k] = phutil_escape_html($v);
      }
      $rows[] = $wire;
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Name',
        'URI',
        'PHID',
        'Priority',
        'Display Name',
        'Display Type',
        'Image URI',
        'Priority Type',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Typeahead Results');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Typeahead Results',
      ));
  }

}

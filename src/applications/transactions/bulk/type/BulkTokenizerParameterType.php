<?php

final class BulkTokenizerParameterType
  extends BulkParameterType {

  private $datasource;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    return $this->datasource;
  }

  public function getPHUIXControlType() {
    return 'tokenizer';
  }

  public function getPHUIXControlSpecification() {
    $template = new AphrontTokenizerTemplateView();
    $template_markup = $template->render();

    $datasource = $this->getDatasource()
      ->setViewer($this->getViewer());

    return array(
      'markup' => (string)hsprintf('%s', $template_markup),
      'config' => array(
        'src' => $datasource->getDatasourceURI(),
        'browseURI' => $datasource->getBrowseURI(),
        'placeholder' => $datasource->getPlaceholderText(),
        'limit' => $datasource->getLimit(),
      ),
      'value' => null,
    );
  }

}

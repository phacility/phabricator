<?php

final class PhabricatorTypeaheadModularDatasourceController
  extends PhabricatorTypeaheadDatasourceController {

  private $class;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->class = idx($data, 'class');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $query = $request->getStr('q');

    // Default this to the query string to make debugging a little bit easier.
    $raw_query = nonempty($request->getStr('raw'), $query);

    // This makes form submission easier in the debug view.
    $this->class = nonempty($request->getStr('class'), $this->class);

    $sources = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorTypeaheadDatasource')
      ->loadObjects();

    if (isset($sources[$this->class])) {
      $source = $sources[$this->class];

      $composite = new PhabricatorTypeaheadRuntimeCompositeDatasource();
      $composite->addDatasource($source);

      $composite
        ->setViewer($viewer)
        ->setQuery($query)
        ->setRawQuery($raw_query);

      $results = $composite->loadResults();
    } else {
      $results = array();
    }

    $content = mpull($results, 'getWireFormat');

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($content);
    }

    // If there's a non-Ajax request to this endpoint, show results in a tabular
    // format to make it easier to debug typeahead output.

    $options = array_fuse(array_keys($sources));
    asort($options);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setAction('/typeahead/class/')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Source Class'))
          ->setName('class')
          ->setValue($this->class)
          ->setOptions($options))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Query'))
          ->setName('q')
          ->setValue($request->getStr('q')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Raw Query'))
          ->setName('raw')
          ->setValue($request->getStr('raw')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Query')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Token Query'))
      ->setForm($form);

    $table = new AphrontTableView($content);
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

    $result_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Token Results (%s)', $this->class))
      ->appendChild($table);

    return $this->buildApplicationPage(
      array(
        $form_box,
        $result_box,
      ),
      array(
        'title' => pht('Typeahead Results'),
        'device' => false,
      ));
  }

}

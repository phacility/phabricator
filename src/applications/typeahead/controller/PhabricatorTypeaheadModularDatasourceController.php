<?php

final class PhabricatorTypeaheadModularDatasourceController
  extends PhabricatorTypeaheadDatasourceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $query = $request->getStr('q');

    // Default this to the query string to make debugging a little bit easier.
    $raw_query = nonempty($request->getStr('raw'), $query);

    // This makes form submission easier in the debug view.
    $class = nonempty($request->getURIData('class'), $request->getStr('class'));

    $sources = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorTypeaheadDatasource')
      ->loadObjects();
    if (isset($sources[$class])) {
      $source = $sources[$class];
      if ($source->getDatasourceApplicationClass()) {
        if (!PhabricatorApplication::isClassInstalledForViewer(
            $source->getDatasourceApplicationClass(),
            $viewer)) {
          return id(new Aphront404Response());
        }
      }

      $source->setParameters($request->getRequestData());

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

    foreach ($sources as $key => $source) {
      // This can happen with composite sources like user or project, as well
      // generic ones like NoOwner
      if (!$source->getDatasourceApplicationClass()) {
        continue;
      }
      if (!PhabricatorApplication::isClassInstalledForViewer(
        $source->getDatasourceApplicationClass(),
        $viewer)) {
        unset($sources[$key]);
      }
    }
    $options = array_fuse(array_keys($sources));
    asort($options);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setAction('/typeahead/class/')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Source Class'))
          ->setName('class')
          ->setValue($class)
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
        pht('Name'),
        pht('URI'),
        pht('PHID'),
        pht('Priority'),
        pht('Display Name'),
        pht('Display Type'),
        pht('Image URI'),
        pht('Priority Type'),
        pht('Icon'),
        pht('Closed'),
        pht('Sprite'),
      ));

    $result_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Token Results (%s)', $class))
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

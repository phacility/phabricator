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
    $is_browse = ($request->getURIData('action') == 'browse');

    // Default this to the query string to make debugging a little bit easier.
    $raw_query = nonempty($request->getStr('raw'), $query);

    // This makes form submission easier in the debug view.
    $class = nonempty($request->getURIData('class'), $request->getStr('class'));

    $sources = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorTypeaheadDatasource')
      ->loadObjects();
    if (isset($sources[$class])) {
      $source = $sources[$class];
      $source->setParameters($request->getRequestData());

      // NOTE: Wrapping the source in a Composite datasource ensures we perform
      // application visibility checks for the viewer, so we do not need to do
      // those separately.
      $composite = new PhabricatorTypeaheadRuntimeCompositeDatasource();
      $composite->addDatasource($source);

      $composite
        ->setViewer($viewer)
        ->setQuery($query)
        ->setRawQuery($raw_query);

      if ($is_browse) {
        $limit = 10;
        $offset = $request->getInt('offset');
        $composite
          ->setLimit($limit + 1)
          ->setOffset($offset);
      }

      $results = $composite->loadResults();

      if ($is_browse) {
        $next_link = null;
        if (count($results) > $limit) {
          $results = array_slice($results, 0, $limit, $preserve_keys = true);
          $next_link = phutil_tag(
            'a',
            array(
              'href' => id(new PhutilURI($request->getRequestURI()))
                ->setQueryParam('offset', $offset + $limit),
            ),
            pht('Next Page'));
        }

        $items = array();
        foreach ($results as $result) {
          $token = PhabricatorTypeaheadTokenView::newForTypeaheadResult(
            $result);
          $items[] = phutil_tag(
            'div',
            array(
              'class' => 'grouped',
            ),
            $token);
        }

        return $this->newDialog()
          ->setWidth(AphrontDialogView::WIDTH_FORM)
          ->setTitle(get_class($source)) // TODO: Provide nice names.
          ->appendChild($items)
          ->appendChild($next_link)
          ->addCancelButton('/', pht('Close'));
      }

    } else if ($is_browse) {
      return new Aphront404Response();
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

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

      $hard_limit = 1000;

      if ($is_browse) {
        $limit = 10;
        $offset = $request->getInt('offset');

        if (($offset + $limit) >= $hard_limit) {
          // Offset-based paging is intrinsically slow; hard-cap how far we're
          // willing to go with it.
          return new Aphront404Response();
        }

        $composite
          ->setLimit($limit + 1)
          ->setOffset($offset);
      }

      $results = $composite->loadResults();

      if ($is_browse) {
        $next_link = null;

        if (count($results) > $limit) {
          $results = array_slice($results, 0, $limit, $preserve_keys = true);
          if (($offset + (2 * $limit)) < $hard_limit) {
            $next_uri = id(new PhutilURI($request->getRequestURI()))
              ->setQueryParam('offset', $offset + $limit);

            $next_link = javelin_tag(
              'a',
              array(
                'href' => $next_uri,
                'class' => 'typeahead-browse-more',
                'sigil' => 'typeahead-browse-more',
                'mustcapture' => true,
              ),
              pht('More Results'));
          } else {
            // If the user has paged through more than 1K results, don't
            // offer to page any further.
            $next_link = javelin_tag(
              'div',
              array(
                'class' => 'typeahead-browse-hard-limit',
              ),
              pht('You reach the edge of the abyss.'));
          }
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

        $markup = array(
          $items,
          $next_link,
        );

        if ($request->isAjax()) {
          $content = array(
            'markup' => hsprintf('%s', $markup),
          );
          return id(new AphrontAjaxResponse())->setContent($content);
        }

        $this->requireResource('typeahead-browse-css');
        $this->initBehavior('typeahead-browse');

        $input_id = celerity_generate_unique_node_id();
        $frame_id = celerity_generate_unique_node_id();

        $config = array(
          'inputID' => $input_id,
          'frameID' => $frame_id,
          'uri' => (string)$request->getRequestURI(),
        );
        $this->initBehavior('typeahead-search', $config);

        $search = javelin_tag(
          'input',
          array(
            'type' => 'text',
            'id' => $input_id,
            'class' => 'typeahead-browse-input',
            'autocomplete' => 'off',
            'placeholder' => $source->getPlaceholderText(),
          ));

        $frame = phutil_tag(
          'div',
          array(
            'class' => 'typeahead-browse-frame',
            'id' => $frame_id,
          ),
          $markup);

        $browser = array(
          phutil_tag(
            'div',
            array(
              'class' => 'typeahead-browse-header',
            ),
            $search),
          $frame,
        );

        return $this->newDialog()
          ->setWidth(AphrontDialogView::WIDTH_FORM)
          ->setRenderDialogAsDiv(true)
          ->setTitle(get_class($source)) // TODO: Provide nice names.
          ->appendChild($browser)
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

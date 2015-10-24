<?php

final class PhabricatorSearchOrderController
  extends PhabricatorSearchBaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $engine_class = $request->getURIData('engine');

    $request->validateCSRF();

    $base_class = 'PhabricatorApplicationSearchEngine';
    if (!is_subclass_of($engine_class, $base_class)) {
      return new Aphront400Response();
    }

    $engine = newv($engine_class, array());
    $engine->setViewer($viewer);

    $queries = $engine->loadAllNamedQueries();
    $queries = mpull($queries, null, 'getQueryKey');

    $order = $request->getStrList('order');
    $queries = array_select_keys($queries, $order) + $queries;

    $sequence = 1;
    foreach ($queries as $query) {
      $query->setSequence($sequence++);
      $query->save();
    }

    return id(new AphrontAjaxResponse());
  }

}

<?php

final class PhabricatorSearchOrderController
  extends PhabricatorSearchBaseController {

  private $engineClass;

  public function willProcessRequest(array $data) {
    $this->engineClass = idx($data, 'engine');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $request->validateCSRF();

    $base_class = 'PhabricatorApplicationSearchEngine';
    if (!is_subclass_of($this->engineClass, $base_class)) {
      return new Aphront400Response();
    }

    $engine = newv($this->engineClass, array());
    $engine->setViewer($user);

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

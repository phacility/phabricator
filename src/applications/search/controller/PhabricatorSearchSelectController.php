<?php

/**
 * @group search
 */
final class PhabricatorSearchSelectController
  extends PhabricatorSearchBaseController {

  private $type;

  public function willProcessRequest(array $data) {
    $this->type = $data['type'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorSearchQuery();
    $query_str = $request->getStr('query');

    $query->setQuery($query_str);
    $query->setParameter('type', $this->type);

    switch ($request->getStr('filter')) {
      case 'assigned':
        $query->setParameter('owner', array($user->getPHID()));
        $query->setParameter('open', 1);
        break;
      case 'created';
        $query->setParameter('author', array($user->getPHID()));
        // TODO - if / when we allow pholio mocks to be archived, etc
        // update this
        if ($this->type != PholioPHIDTypeMock::TYPECONST) {
          $query->setParameter('open', 1);
        }
        break;
      case 'open':
        $query->setParameter('open', 1);
        break;
    }

    $query->setParameter('exclude', $request->getStr('exclude'));
    $query->setParameter('limit', 100);

    $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
    $results = $engine->executeSearch($query);

    $phids = array_fill_keys($results, true);
    $phids += $this->queryObjectNames($query_str);

    $phids = array_keys($phids);
    $handles = $this->loadViewerHandles($phids);

    $data = array();
    foreach ($handles as $handle) {
      $view = new PhabricatorHandleObjectSelectorDataView($handle);
      $data[] = $view->renderData();
    }

    return id(new AphrontAjaxResponse())->setContent($data);
  }

  private function queryObjectNames($query) {
    $viewer = $this->getRequest()->getUser();

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withTypes(array($this->type))
      ->withNames(array($query))
      ->execute();

    return mpull($objects, 'getPHID');
  }

}

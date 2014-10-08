<?php

final class PhabricatorSearchSelectController
  extends PhabricatorSearchBaseController {

  private $type;

  public function willProcessRequest(array $data) {
    $this->type = $data['type'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorSavedQuery();
    $query_str = $request->getStr('query');

    $query->setEngineClassName('PhabricatorSearchApplicationSearchEngine');
    $query->setParameter('query', $query_str);
    $query->setParameter('types', array($this->type));

    $status_open = PhabricatorSearchRelationship::RELATIONSHIP_OPEN;

    switch ($request->getStr('filter')) {
      case 'assigned':
        $query->setParameter('ownerPHIDs', array($user->getPHID()));
        $query->setParameter('statuses', array($status_open));
        break;
      case 'created';
        $query->setParameter('authorPHIDs', array($user->getPHID()));
        // TODO - if / when we allow pholio mocks to be archived, etc
        // update this
        if ($this->type != PholioMockPHIDType::TYPECONST) {
          $query->setParameter('statuses', array($status_open));
        }
        break;
      case 'open':
        $query->setParameter('statuses', array($status_open));
        break;
    }

    $query->setParameter('excludePHIDs', array($request->getStr('exclude')));

    $results = id(new PhabricatorSearchDocumentQuery())
      ->setViewer($user)
      ->withSavedQuery($query)
      ->setOffset(0)
      ->setLimit(100)
      ->execute();

    $phids = array_fill_keys(mpull($results, 'getPHID'), true);
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

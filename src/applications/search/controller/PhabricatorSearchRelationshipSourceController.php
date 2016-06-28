<?php

final class PhabricatorSearchRelationshipSourceController
  extends PhabricatorSearchBaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $object = $this->loadRelationshipObject();
    if (!$object) {
      return new Aphront404Response();
    }

    $relationship = $this->loadRelationship($object);
    if (!$relationship) {
      return new Aphront404Response();
    }

    $source = $relationship->newSource();
    $query = new PhabricatorSavedQuery();

    $action = $request->getURIData('action');
    $query_str = $request->getStr('query');
    $filter = $request->getStr('filter');

    $query->setEngineClassName('PhabricatorSearchApplicationSearchEngine');
    $query->setParameter('query', $query_str);

    $types = $source->getResultPHIDTypes();
    $query->setParameter('types', $types);

    $status_open = PhabricatorSearchRelationship::RELATIONSHIP_OPEN;

    switch ($filter) {
      case 'assigned':
        $query->setParameter('ownerPHIDs', array($viewer->getPHID()));
        $query->setParameter('statuses', array($status_open));
        break;
      case 'created';
        $query->setParameter('authorPHIDs', array($viewer->getPHID()));
        $query->setParameter('statuses', array($status_open));
        break;
      case 'open':
        $query->setParameter('statuses', array($status_open));
        break;
    }

    $query->setParameter('excludePHIDs', array($request->getStr('exclude')));

    $capabilities = $relationship->getRequiredRelationshipCapabilities();

    $results = id(new PhabricatorSearchDocumentQuery())
      ->setViewer($viewer)
      ->requireObjectCapabilities($capabilities)
      ->withSavedQuery($query)
      ->setOffset(0)
      ->setLimit(100)
      ->execute();

    $phids = array_fill_keys(mpull($results, 'getPHID'), true);
    $phids += $this->queryObjectNames($query_str, $capabilities);

    $phids = array_keys($phids);
    $handles = $viewer->loadHandles($phids);

    $data = array();
    foreach ($handles as $handle) {
      $view = new PhabricatorHandleObjectSelectorDataView($handle);
      $data[] = $view->renderData();
    }

    return id(new AphrontAjaxResponse())->setContent($data);
  }

  private function queryObjectNames($query, $capabilities) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities($capabilities)
      ->withTypes(array($request->getURIData('type')))
      ->withNames(array($query))
      ->execute();

    return mpull($objects, 'getPHID');
  }

}

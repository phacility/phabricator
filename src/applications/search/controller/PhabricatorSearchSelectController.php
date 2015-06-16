<?php

final class PhabricatorSearchSelectController
  extends PhabricatorSearchBaseController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $type = $request->getURIData('type');
    $action = $request->getURIData('action');

    $query = new PhabricatorSavedQuery();
    $query_str = $request->getStr('query');

    $query->setEngineClassName('PhabricatorSearchApplicationSearchEngine');
    $query->setParameter('query', $query_str);
    $query->setParameter('types', array($type));

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
        if ($type != PholioMockPHIDType::TYPECONST) {
          $query->setParameter('statuses', array($status_open));
        }
        break;
      case 'open':
        $query->setParameter('statuses', array($status_open));
        break;
    }

    $query->setParameter('excludePHIDs', array($request->getStr('exclude')));

    $capabilities = array(PhabricatorPolicyCapability::CAN_VIEW);
    switch ($action) {
      case self::ACTION_MERGE:
        $capabilities[] = PhabricatorPolicyCapability::CAN_EDIT;
        break;
      default:
        break;
    }

    $results = id(new PhabricatorSearchDocumentQuery())
      ->setViewer($user)
      ->requireObjectCapabilities($capabilities)
      ->withSavedQuery($query)
      ->setOffset(0)
      ->setLimit(100)
      ->execute();

    $phids = array_fill_keys(mpull($results, 'getPHID'), true);
    $phids += $this->queryObjectNames($query_str, $capabilities);

    $phids = array_keys($phids);
    $handles = $this->loadViewerHandles($phids);

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

<?php

final class PhabricatorProjectBoardReloadController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $state = $this->getViewState();
    $board_uri = $state->newWorkboardURI();

    $layout_engine = $state->getLayoutEngine();

    $board_phid = $project->getPHID();

    $objects = $state->getObjects();
    $objects = mpull($objects, null, 'getPHID');

    try {
      $client_state = $request->getStr('state');
      $client_state = phutil_json_decode($client_state);
    } catch (PhutilJSONParserException $ex) {
      $client_state = array();
    }

    // Figure out which objects need to be updated: either the client has an
    // out-of-date version of them (objects which have been edited); or they
    // exist on the client but not on the server (objects which have been
    // removed from the board); or they exist on the server but not on the
    // client (objects which have been added to the board).

    $update_objects = array();
    foreach ($objects as $object_phid => $object) {

      // TODO: For now, this is always hard-coded.
      $object_version = 2;

      $client_version = idx($client_state, $object_phid, 0);
      if ($object_version > $client_version) {
        $update_objects[$object_phid] = $object;
      }
    }

    $update_phids = array_keys($update_objects);
    $visible_phids = array_keys($client_state);

    $engine = id(new PhabricatorBoardResponseEngine())
      ->setViewer($viewer)
      ->setBoardPHID($board_phid)
      ->setObjects($objects)
      ->setUpdatePHIDs($update_phids)
      ->setVisiblePHIDs($visible_phids);

    // TODO: We don't currently process "order" properly. If a user is viewing
    // a board grouped by "Owner", and another user changes a task to be owned
    // by a user who currently owns nothing on the board, the new header won't
    // generate correctly if the first user presses "R".

    return $engine->buildResponse();
  }

}

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
    $object_phids = mpull($objects, 'getPHID');

    $engine = id(new PhabricatorBoardResponseEngine())
      ->setViewer($viewer)
      ->setBoardPHID($board_phid)
      ->setUpdatePHIDs($object_phids);

    // TODO: We don't currently process "order" properly. If a user is viewing
    // a board grouped by "Owner", and another user changes a task to be owned
    // by a user who currently owns nothing on the board, the new header won't
    // generate correctly if the first user presses "R".

    return $engine->buildResponse();
  }

}

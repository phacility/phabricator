<?php

final class ManiphestBulkEditController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      ManiphestBulkEditCapability::CAPABILITY);

    $bulk_engine = id(new ManiphestTaskBulkEngine())
      ->setViewer($viewer)
      ->setController($this)
      ->addContextParameter('board');

    $board_id = $request->getInt('board');
    if ($board_id) {
      $project = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withIDs(array($board_id))
        ->executeOne();
      if (!$project) {
        return new Aphront404Response();
      }

      $bulk_engine->setWorkboard($project);
    }

    return $bulk_engine->buildResponse();
  }

}

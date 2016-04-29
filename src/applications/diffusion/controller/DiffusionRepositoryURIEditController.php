<?php

final class DiffusionRepositoryURIEditController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    return id(new DiffusionURIEditEngine())
      ->setController($this)
      ->setRepository($repository)
      ->buildResponse();
  }

}

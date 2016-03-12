<?php

final class NuanceItemListController
  extends NuanceItemController {

  public function handleRequest(AphrontRequest $request) {
    return id(new NuanceItemSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}

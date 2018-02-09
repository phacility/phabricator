<?php

final class HeraldWebhookEditController
  extends HeraldWebhookController {

  public function handleRequest(AphrontRequest $request) {
    return id(new HeraldWebhookEditEngine())
      ->setController($this)
      ->buildResponse();
  }

}

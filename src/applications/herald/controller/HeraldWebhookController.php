<?php

abstract class HeraldWebhookController extends HeraldController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Webhooks'),
      $this->getApplicationURI('webhook/'));

    return $crumbs;
  }

}

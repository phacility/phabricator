<?php

abstract class PhortuneStripeBaseController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Phortune - Stripe');
    $page->setBaseURI('/phortune/stripe/');
    $page->setTitle(idx($data, 'title'));
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}

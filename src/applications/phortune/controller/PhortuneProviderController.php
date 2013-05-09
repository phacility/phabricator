<?php

final class PhortuneProviderController extends PhortuneController {

  private $digest;
  private $action;

  public function willProcessRequest(array $data) {
    $this->digest = $data['digest'];
    $this->setAction($data['action']);
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function getAction() {
    return $this->action;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();


    // NOTE: This use of digests to identify payment providers is because
    // payment provider keys don't necessarily have restrictions on what they
    // contain (so they might have stuff that's not safe to put in URIs), and
    // using digests prevents errors with URI encoding.

    $provider = PhortunePaymentProvider::getProviderByDigest($this->digest);
    if (!$provider) {
      throw new Exception("Invalid payment provider digest!");
    }

    if (!$provider->canRespondToControllerAction($this->getAction())) {
      return new Aphront404Response();
    }


    $response = $provider->processControllerRequest($this, $request);

    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $title = 'Phortune';

    return $this->buildApplicationPage(
      $response,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }


  public function loadCart($id) {
    return id(new PhortuneCart());
  }

}

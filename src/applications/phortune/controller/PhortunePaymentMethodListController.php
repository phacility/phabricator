<?php

final class PhortunePaymentMethodListController extends PhabricatorController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $title = pht('Payment Methods');
    $crumbs = $this->buildApplicationCrumbs();

    return $this->buildApplicationPage(
      array(
        $crumbs,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

}

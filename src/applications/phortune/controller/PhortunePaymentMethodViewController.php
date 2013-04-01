<?php

final class PhortunePaymentMethodViewController extends PhabricatorController {

  public function processRequest() {

    $title = '...';
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

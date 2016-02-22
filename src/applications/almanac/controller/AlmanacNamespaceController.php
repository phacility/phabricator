<?php

abstract class AlmanacNamespaceController extends AlmanacController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $list_uri = $this->getApplicationURI('namespace/');
    $crumbs->addTextCrumb(pht('Namespaces'), $list_uri);

    return $crumbs;
  }

}

<?php

/**
 * Details about a routing map match for a path.
 *
 * @param info Result Information
 */
final class AphrontRoutingResult extends Phobject {

  private $site;
  private $application;
  private $controller;
  private $uriData;


/* -(  Result Information  )------------------------------------------------- */


  public function setSite(AphrontSite $site) {
    $this->site = $site;
    return $this;
  }

  public function getSite() {
    return $this->site;
  }

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function getApplication() {
    return $this->application;
  }

  public function setController(AphrontController $controller) {
    $this->controller = $controller;
    return $this;
  }

  public function getController() {
    return $this->controller;
  }

  public function setURIData(array $uri_data) {
    $this->uriData = $uri_data;
    return $this;
  }

  public function getURIData() {
    return $this->uriData;
  }

}

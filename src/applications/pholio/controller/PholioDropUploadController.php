<?php

/**
 * @group pholio
 */
final class PholioDropUploadController extends PholioController {

  public function processRequest() {
    return $this->delegateToController(
      id(new PhabricatorFileDropUploadController($this->getRequest()))
      ->setViewObject(new PholioUploadedImageView()));
  }

}

<?php

abstract class PholioImageTransactionType
  extends PholioTransactionType {

  protected function getImageForXaction(PholioMock $mock) {
    $raw_new_value = $this->getNewValue();
    $image_phid = head_key($raw_new_value);
    $images = $mock->getImages();
    foreach ($images as $image) {
      if ($image->getPHID() == $image_phid) {
        return $image;
      }
    }
    return null;
  }

}

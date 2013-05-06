<?php

final class PhabricatorFileTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function generate() {
    $authorPHID = $this->loadAuthorPHID();
    $dimension = 1 << rand(5, 12);
    $image = id(new PhabricatorLipsumMondrianArtist())
      ->generate($dimension, $dimension);
    $file = PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'rand-'.rand(1000, 9999),
      ));
    $file->setAuthorPHID($authorPHID);
    $file->setMimeType('image/jpeg');
    return $file->save();
  }

  private function loadPhabrictorUserPHID() {
    return $this->loadOneRandom("PhabricatorUser")->getPHID();
  }

  public function loadAuthorPHID() {
    return $this->loadPhabrictorUserPHID();
  }
}

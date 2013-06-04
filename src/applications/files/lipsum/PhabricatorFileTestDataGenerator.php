<?php

final class PhabricatorFileTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function generate() {
    $authorPHID = $this->loadPhabrictorUserPHID();
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
}

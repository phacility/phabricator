<?php

final class PhabricatorProjectProfile extends PhabricatorProjectDAO {

  protected $projectPHID;
  protected $blurb;
  protected $profileImagePHID;

  private $profileImageFile = self::ATTACHABLE;

  public function getProfileImageURI() {
    return $this->getProfileImageFile()->getBestURI();
  }

  public function attachProfileImageFile(PhabricatorFile $file) {
    $this->profileImageFile = $file;
    return $this;
  }

  public function getProfileImageFile() {
    return $this->assertAttached($this->profileImageFile);
  }

}

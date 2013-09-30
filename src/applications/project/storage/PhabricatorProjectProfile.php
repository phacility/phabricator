<?php

final class PhabricatorProjectProfile extends PhabricatorProjectDAO {

  protected $projectPHID;
  protected $blurb;
  protected $profileImagePHID;

  public function loadProfileImageURI() {
    $src_phid = $this->getProfileImagePHID();

    // TODO: (T603) Can we get rid of this and move it to a Query?
    $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $src_phid);
    if ($file) {
      return $file->getBestURI();
    }

    return PhabricatorUser::getDefaultProfileImageURI();
  }

}

<?php

final class PhabricatorFileImageMacro extends PhabricatorFileDAO
  implements PhabricatorSubscribableInterface {

  protected $filePHID;
  protected $phid;
  protected $name;
  protected $isDisabled = 0;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID  => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_MCRO);
  }

  static public function newFromImageURI($uri, $file_name, $image_macro_name) {
    $file = PhabricatorFile::newFromFileDownload($uri, $file_name);

    if (!$file) {
      return null;
    }

    $image_macro = new PhabricatorFileImageMacro();
    $image_macro->setName($image_macro_name);
    $image_macro->setFilePHID($file->getPHID());
    $image_macro->save();

    return $image_macro;
  }

  public function isAutomaticallySubscribed($phid) {
    return false;
  }

}


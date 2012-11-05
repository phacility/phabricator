<?php

final class PhabricatorFileImageMacro extends PhabricatorFileDAO {

  protected $filePHID;
  protected $name;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
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
}


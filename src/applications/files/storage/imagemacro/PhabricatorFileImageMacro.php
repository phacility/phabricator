<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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


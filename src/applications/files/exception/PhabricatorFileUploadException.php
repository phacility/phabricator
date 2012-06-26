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

final class PhabricatorFileUploadException extends Exception {

  public function __construct($code) {
    $map = array(
      UPLOAD_ERR_INI_SIZE =>
        "Uploaded file is too large: file is larger than the ".
        "'upload_max_filesize' setting in php.ini.",
      UPLOAD_ERR_FORM_SIZE =>
        "File is too large.",
      UPLOAD_ERR_PARTIAL =>
        "File was only partially transferred, upload did not complete.",
      UPLOAD_ERR_NO_FILE =>
        "No file was uploaded.",
      UPLOAD_ERR_NO_TMP_DIR =>
        "Unable to write file: temporary directory does not exist.",
      UPLOAD_ERR_CANT_WRITE =>
        "Unable to write file: failed to write to temporary directory.",
      UPLOAD_ERR_EXTENSION =>
        "Unable to upload: a PHP extension stopped the upload.",

      -1000 =>
        "Uploaded file exceeds limit in Phabricator ".
        "'storage.upload-size-limit' configuration.",
    );

    $message = idx($map, $code, "Upload failed: unknown error.");
    parent::__construct($message, $code);
  }
}

<?php

final class PhabricatorFileUploadException extends Exception {

  public function __construct($code) {
    $map = array(
      UPLOAD_ERR_INI_SIZE => pht(
        "Uploaded file is too large: current limit is %s. To adjust ".
        "this limit change '%s' in php.ini.",
        ini_get('upload_max_filesize'),
        'upload_max_filesize'),
      UPLOAD_ERR_FORM_SIZE => pht(
        'File is too large.'),
      UPLOAD_ERR_PARTIAL => pht(
        'File was only partially transferred, upload did not complete.'),
      UPLOAD_ERR_NO_FILE => pht(
        'No file was uploaded.'),
      UPLOAD_ERR_NO_TMP_DIR => pht(
        'Unable to write file: temporary directory does not exist.'),
      UPLOAD_ERR_CANT_WRITE => pht(
        'Unable to write file: failed to write to temporary directory.'),
      UPLOAD_ERR_EXTENSION => pht(
        'Unable to upload: a PHP extension stopped the upload.'),
    );

    $message = idx(
      $map,
      $code,
      pht('Upload failed: unknown error (%s).', $code));
    parent::__construct($message, $code);
  }
}

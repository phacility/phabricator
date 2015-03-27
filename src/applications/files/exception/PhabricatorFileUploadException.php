<?php

final class PhabricatorFileUploadException extends Exception {

  public function __construct($code) {
    $map = array(
      UPLOAD_ERR_INI_SIZE =>
        pht("Uploaded file is too large: current limit is %s. To adjust ".
          "this limit change 'upload_max_filesize' in php.ini.",
          ini_get('upload_max_filesize')),
      UPLOAD_ERR_FORM_SIZE =>
        'File is too large.',
      UPLOAD_ERR_PARTIAL =>
        'File was only partially transferred, upload did not complete.',
      UPLOAD_ERR_NO_FILE =>
        'No file was uploaded.',
      UPLOAD_ERR_NO_TMP_DIR =>
        'Unable to write file: temporary directory does not exist.',
      UPLOAD_ERR_CANT_WRITE =>
        'Unable to write file: failed to write to temporary directory.',
      UPLOAD_ERR_EXTENSION =>
        'Unable to upload: a PHP extension stopped the upload.',
    );

    $message = idx($map, $code, 'Upload failed: unknown error.');
    parent::__construct($message, $code);
  }
}

<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorFile extends PhabricatorFileDAO {

  const STORAGE_ENGINE_BLOB = 'blob';

  const STORAGE_FORMAT_RAW  = 'raw';

  // TODO: We need to reconcile this with MySQL packet size.
  const FILE_SIZE_BYTE_LIMIT = 12582912;

  protected $phid;
  protected $name;
  protected $mimeType;
  protected $byteSize;

  protected $storageEngine;
  protected $storageFormat;
  protected $storageHandle;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_FILE);
  }

  public static function newFromPHPUpload($spec, array $params = array()) {
    if (!$spec) {
      throw new Exception("No file was uploaded!");
    }

    $err = idx($spec, 'error');
    if ($err) {
      throw new Exception("File upload failed with error '{$err}'.");
    }

    $tmp_name = idx($spec, 'tmp_name');
    $is_valid = @is_uploaded_file($tmp_name);
    if (!$is_valid) {
      throw new Exception("File is not an uploaded file.");
    }

    $file_data = Filesystem::readFile($tmp_name);
    $file_size = idx($spec, 'size');

    if (strlen($file_data) != $file_size) {
      throw new Exception("File size disagrees with uploaded size.");
    }

    $file_name = nonempty(
      idx($params, 'name'),
      idx($spec,   'name'));
    $params = array(
      'name' => $file_name,
    ) + $params;

    return self::newFromFileData($file_data, $params);
  }

  public static function newFromFileData($data, array $params = array()) {
    $file_size = strlen($data);

    if ($file_size > self::FILE_SIZE_BYTE_LIMIT) {
      throw new Exception("File is too large to store.");
    }

    $file_name = idx($params, 'name');
    $file_name = self::normalizeFileName($file_name);

    $file = new PhabricatorFile();
    $file->setName($file_name);
    $file->setByteSize(strlen($data));

    $blob = new PhabricatorFileStorageBlob();
    $blob->setData($data);
    $blob->save();

    // TODO: This stuff is almost certainly YAGNI, but we could imagine having
    // an alternate disk store and gzipping or encrypting things or something
    // crazy like that and this isn't toooo much extra code.
    $file->setStorageEngine(self::STORAGE_ENGINE_BLOB);
    $file->setStorageFormat(self::STORAGE_FORMAT_RAW);
    $file->setStorageHandle($blob->getID());

    if (isset($params['mime-type'])) {
      $file->setMimeType($params['mime-type']);
    } else {
      try {
        $tmp = new TempFile();
        Filesystem::writeFile($tmp, $data);
        list($stdout) = execx('file -b --mime %s', $tmp);
        $file->setMimeType($stdout);
      } catch (Exception $ex) {
        // Be robust here since we don't really care that much about mime types.
      }
    }

    $file->save();

    return $file;
  }

  public static function newFromFileDownload($uri, $name) {
    $uri = new PhutilURI($uri);

    $protocol = $uri->getProtocol();
    switch ($protocol) {
      case 'http':
      case 'https':
        break;
      default:
        // Make sure we are not accessing any file:// URIs or similar.
        return null;
    }

    $timeout = stream_context_create(
      array(
        'http' => array(
          'timeout' => 5,
        ),
      ));

    $file_data = @file_get_contents($uri, false, $timeout);
    if ($file_data === false) {
      return null;
    }

    return self::newFromFileData($file_data, array('name' => $name));
  }

  public static function normalizeFileName($file_name) {
    return preg_replace('/[^a-zA-Z0-9.~_-]/', '_', $file_name);
  }

  public function delete() {
    $this->openTransaction();
      switch ($this->getStorageEngine()) {
        case self::STORAGE_ENGINE_BLOB:
          $handle = $this->getStorageHandle();
          $blob = id(new PhabricatorFileStorageBlob())->load($handle);
          $blob->delete();
          break;
        default:
          throw new Exception("Unknown storage engine!");
      }

      $ret = parent::delete();
    $this->saveTransaction();
    return $ret;
  }

  public function loadFileData() {

    $handle = $this->getStorageHandle();
    $data   = null;

    switch ($this->getStorageEngine()) {
      case self::STORAGE_ENGINE_BLOB:
        $blob = id(new PhabricatorFileStorageBlob())->load($handle);
        if (!$blob) {
          throw new Exception("Failed to load file blob data.");
        }
        $data = $blob->getData();
        break;
      default:
        throw new Exception("Unknown storage engine.");
    }

    switch ($this->getStorageFormat()) {
      case self::STORAGE_FORMAT_RAW:
        $data = $data;
        break;
      default:
        throw new Exception("Unknown storage format.");
    }

    return $data;
  }

  public function getViewURI() {
    return PhabricatorFileURI::getViewURIForPHID($this->getPHID());
  }

  public function isViewableInBrowser() {
    return ($this->getViewableMimeType() !== null);
  }

  public function getViewableMimeType() {
    $mime_map = PhabricatorEnv::getEnvConfig('files.viewable-mime-types');

    $mime_type = $this->getMimeType();
    $mime_parts = explode(';', $mime_type);
    $mime_type = trim(reset($mime_parts));

    return idx($mime_map, $mime_type);
  }

}

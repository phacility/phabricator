<?php

final class PhabricatorFile extends PhabricatorFileDAO
  implements PhabricatorPolicyInterface {

  const STORAGE_FORMAT_RAW  = 'raw';

  const METADATA_IMAGE_WIDTH  = 'width';
  const METADATA_IMAGE_HEIGHT = 'height';

  protected $phid;
  protected $name;
  protected $mimeType;
  protected $byteSize;
  protected $authorPHID;
  protected $secretKey;
  protected $contentHash;
  protected $metadata = array();

  protected $storageEngine;
  protected $storageFormat;
  protected $storageHandle;

  protected $ttl;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_FILE);
  }

  public static function readUploadedFileData($spec) {
    if (!$spec) {
      throw new Exception("No file was uploaded!");
    }

    $err = idx($spec, 'error');
    if ($err) {
      throw new PhabricatorFileUploadException($err);
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

    self::validateFileSize(strlen($file_data));

    return $file_data;
  }

  public static function newFromPHPUpload($spec, array $params = array()) {
    $file_data = self::readUploadedFileData($spec);

    $file_name = nonempty(
      idx($params, 'name'),
      idx($spec,   'name'));
    $params = array(
      'name' => $file_name,
    ) + $params;

    return self::newFromFileData($file_data, $params);
  }

  public static function newFromXHRUpload($data, array $params = array()) {
    self::validateFileSize(strlen($data));
    return self::newFromFileData($data, $params);
  }

  private static function validateFileSize($size) {
    $limit = PhabricatorEnv::getEnvConfig('storage.upload-size-limit');
    if (!$limit) {
      return;
    }

    $limit = phabricator_parse_bytes($limit);
    if ($size > $limit) {
      throw new PhabricatorFileUploadException(-1000);
    }
  }


  /**
   * Given a block of data, try to load an existing file with the same content
   * if one exists. If it does not, build a new file.
   *
   * This method is generally used when we have some piece of semi-trusted data
   * like a diff or a file from a repository that we want to show to the user.
   * We can't just dump it out because it may be dangerous for any number of
   * reasons; instead, we need to serve it through the File abstraction so it
   * ends up on the CDN domain if one is configured and so on. However, if we
   * simply wrote a new file every time we'd potentially end up with a lot
   * of redundant data in file storage.
   *
   * To solve these problems, we use file storage as a cache and reuse the
   * same file again if we've previously written it.
   *
   * NOTE: This method unguards writes.
   *
   * @param string  Raw file data.
   * @param dict    Dictionary of file information.
   */
  public static function buildFromFileDataOrHash(
    $data,
    array $params = array()) {

    $file = id(new PhabricatorFile())->loadOneWhere(
      'name = %s AND contentHash = %s LIMIT 1',
      self::normalizeFileName(idx($params, 'name')),
      self::hashFileContent($data));

    if (!$file) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file = PhabricatorFile::newFromFileData($data, $params);
      unset($unguarded);
    }

    return $file;
  }

  public static function newFileFromContentHash($hash, $params) {

    // Check to see if a file with same contentHash exist
    $file = id(new PhabricatorFile())->loadOneWhere(
      'contentHash = %s LIMIT 1', $hash);

    if ($file) {
      // copy storageEngine, storageHandle, storageFormat
      $copy_of_storage_engine = $file->getStorageEngine();
      $copy_of_storage_handle = $file->getStorageHandle();
      $copy_of_storage_format = $file->getStorageFormat();
      $copy_of_byteSize = $file->getByteSize();
      $copy_of_mimeType = $file->getMimeType();

      $file_name = idx($params, 'name');
      $file_name = self::normalizeFileName($file_name);
      $file_ttl = idx($params, 'ttl');
      $authorPHID = idx($params, 'authorPHID');

      $new_file = new  PhabricatorFile();

      $new_file->setName($file_name);
      $new_file->setByteSize($copy_of_byteSize);
      $new_file->setAuthorPHID($authorPHID);
      $new_file->setTtl($file_ttl);

      $new_file->setContentHash($hash);
      $new_file->setStorageEngine($copy_of_storage_engine);
      $new_file->setStorageHandle($copy_of_storage_handle);
      $new_file->setStorageFormat($copy_of_storage_format);
      $new_file->setMimeType($copy_of_mimeType);

      $new_file->save();

      return $new_file;
    }

    return $file;
  }

  private static function buildFromFileData($data, array $params = array()) {
    $selector = PhabricatorEnv::newObjectFromConfig('storage.engine-selector');

    if (isset($params['storageEngines'])) {
      $engines = $params['storageEngines'];
    } else {
      $selector = PhabricatorEnv::newObjectFromConfig(
        'storage.engine-selector');
      $engines = $selector->selectStorageEngines($data, $params);
    }

    assert_instances_of($engines, 'PhabricatorFileStorageEngine');
    if (!$engines) {
      throw new Exception("No valid storage engines are available!");
    }

    $file = new PhabricatorFile();

    $data_handle = null;
    $engine_identifier = null;
    $exceptions = array();
    foreach ($engines as $engine) {
      $engine_class = get_class($engine);
      try {
        list($engine_identifier, $data_handle) = $file->writeToEngine(
          $engine,
          $data,
          $params);

        // We stored the file somewhere so stop trying to write it to other
        // places.
        break;
      } catch (PhabricatorFileStorageConfigurationException $ex) {
        // If an engine is outright misconfigured (or misimplemented), raise
        // that immediately since it probably needs attention.
        throw $ex;
      } catch (Exception $ex) {
        phlog($ex);

        // If an engine doesn't work, keep trying all the other valid engines
        // in case something else works.
        $exceptions[$engine_class] = $ex;
      }
    }

    if (!$data_handle) {
      throw new PhutilAggregateException(
        "All storage engines failed to write file:",
        $exceptions);
    }

    $file_name = idx($params, 'name');
    $file_name = self::normalizeFileName($file_name);
    $file_ttl = idx($params, 'ttl');

    // If for whatever reason, authorPHID isn't passed as a param
    // (always the case with newFromFileDownload()), store a ''
    $authorPHID = idx($params, 'authorPHID');

    $file->setName($file_name);
    $file->setByteSize(strlen($data));
    $file->setAuthorPHID($authorPHID);
    $file->setTtl($file_ttl);
    $file->setContentHash(self::hashFileContent($data));

    $file->setStorageEngine($engine_identifier);
    $file->setStorageHandle($data_handle);

    // TODO: This is probably YAGNI, but allows for us to do encryption or
    // compression later if we want.
    $file->setStorageFormat(self::STORAGE_FORMAT_RAW);

    if (isset($params['mime-type'])) {
      $file->setMimeType($params['mime-type']);
    } else {
      $tmp = new TempFile();
      Filesystem::writeFile($tmp, $data);
      $file->setMimeType(Filesystem::getMimeType($tmp));
    }

    try {
      $file->updateDimensions(false);
    } catch (Exception $ex) {
      // Do nothing
    }

    $file->save();

    return $file;
  }

  public static function newFromFileData($data, array $params = array()) {
    $hash = self::hashFileContent($data);
    $file = self::newFileFromContentHash($hash, $params);

    if ($file) {
      return $file;
    }

    return self::buildFromFileData($data, $params);
  }

  public function migrateToEngine(PhabricatorFileStorageEngine $engine) {
    if (!$this->getID() || !$this->getStorageHandle()) {
      throw new Exception(
        "You can not migrate a file which hasn't yet been saved.");
    }

    $data = $this->loadFileData();
    $params = array(
      'name' => $this->getName(),
    );

    list($new_identifier, $new_handle) = $this->writeToEngine(
      $engine,
      $data,
      $params);

    $old_engine = $this->instantiateStorageEngine();
    $old_handle = $this->getStorageHandle();

    $this->setStorageEngine($new_identifier);
    $this->setStorageHandle($new_handle);
    $this->save();

    $old_engine->deleteFile($old_handle);

    return $this;
  }

  private function writeToEngine(
    PhabricatorFileStorageEngine $engine,
    $data,
    array $params) {

    $engine_class = get_class($engine);

    $data_handle = $engine->writeFile($data, $params);

    if (!$data_handle || strlen($data_handle) > 255) {
      // This indicates an improperly implemented storage engine.
      throw new PhabricatorFileStorageConfigurationException(
        "Storage engine '{$engine_class}' executed writeFile() but did ".
        "not return a valid handle ('{$data_handle}') to the data: it ".
        "must be nonempty and no longer than 255 characters.");
    }

    $engine_identifier = $engine->getEngineIdentifier();
    if (!$engine_identifier || strlen($engine_identifier) > 32) {
      throw new PhabricatorFileStorageConfigurationException(
        "Storage engine '{$engine_class}' returned an improper engine ".
        "identifier '{$engine_identifier}': it must be nonempty ".
        "and no longer than 32 characters.");
    }

    return array($engine_identifier, $data_handle);
  }


  public static function newFromFileDownload($uri, array $params = array()) {
    // Make sure we're allowed to make a request first
    if (!PhabricatorEnv::getEnvConfig('security.allow-outbound-http')) {
      throw new Exception("Outbound HTTP requests are disabled!");
    }

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

    $timeout = 5;

    list($file_data) = id(new HTTPSFuture($uri))
        ->setTimeout($timeout)
        ->resolvex();

    $params = $params + array(
      'name' => basename($uri),
    );

    return self::newFromFileData($file_data, $params);
  }

  public static function normalizeFileName($file_name) {
    return preg_replace('/[^a-zA-Z0-9.~_-]/', '_', $file_name);
  }

  public function delete() {
    // delete all records of this file in transformedfile
    $trans_files = id(new PhabricatorTransformedFile())->loadAllWhere(
      'TransformedPHID = %s', $this->getPHID());

    $this->openTransaction();
    foreach ($trans_files as $trans_file) {
      $trans_file->delete();
    }
    $ret = parent::delete();
    $this->saveTransaction();

    // Check to see if other files are using storage
    $other_file = id(new PhabricatorFile())->loadAllWhere(
      'storageEngine = %s AND storageHandle = %s AND
      storageFormat = %s AND id != %d LIMIT 1', $this->getStorageEngine(),
      $this->getStorageHandle(), $this->getStorageFormat(),
      $this->getID());

    // If this is the only file using the storage, delete storage
    if (count($other_file) == 0) {
      $engine = $this->instantiateStorageEngine();
      $engine->deleteFile($this->getStorageHandle());
    }
    return $ret;
  }

  public static function hashFileContent($data) {
    return sha1($data);
  }

  public function loadFileData() {

    $engine = $this->instantiateStorageEngine();
    $data = $engine->readFile($this->getStorageHandle());

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
    if (!$this->getPHID()) {
      throw new Exception(
        "You must save a file before you can generate a view URI.");
    }

    $name = phutil_escape_uri($this->getName());

    $path = '/file/data/'.$this->getSecretKey().'/'.$this->getPHID().'/'.$name;
    return PhabricatorEnv::getCDNURI($path);
  }

  public function getInfoURI() {
    return '/file/info/'.$this->getPHID().'/';
  }

  public function getBestURI() {
    if ($this->isViewableInBrowser()) {
      return $this->getViewURI();
    } else {
      return $this->getInfoURI();
    }
  }

  public function getDownloadURI() {
    $uri = id(new PhutilURI($this->getViewURI()))
      ->setQueryParam('download', true);
    return (string) $uri;
  }

  public function getThumb60x45URI() {
    $path = '/file/xform/thumb-60x45/'.$this->getPHID().'/'
      .$this->getSecretKey().'/';
    return PhabricatorEnv::getCDNURI($path);
  }

  public function getThumb160x120URI() {
    $path = '/file/xform/thumb-160x120/'.$this->getPHID().'/'
      .$this->getSecretKey().'/';
    return PhabricatorEnv::getCDNURI($path);
  }

  public function getPreview140URI() {
    $path = '/file/xform/preview-140/'.$this->getPHID().'/'
      .$this->getSecretKey().'/';
    return PhabricatorEnv::getCDNURI($path);
  }

  public function getPreview220URI() {
    $path = '/file/xform/preview-220/'.$this->getPHID().'/'
      .$this->getSecretKey().'/';
    return PhabricatorEnv::getCDNURI($path);
  }

  public function getThumb220x165URI() {
    $path = '/file/xform/thumb-220x165/'.$this->getPHID().'/'
      .$this->getSecretKey().'/';
    return PhabricatorEnv::getCDNURI($path);
  }

  public function getThumb280x210URI() {
    $path = '/file/xform/thumb-280x210/'.$this->getPHID().'/'
      .$this->getSecretKey().'/';
    return PhabricatorEnv::getCDNURI($path);
  }

  public function isViewableInBrowser() {
    return ($this->getViewableMimeType() !== null);
  }

  public function isViewableImage() {
    if (!$this->isViewableInBrowser()) {
      return false;
    }

    $mime_map = PhabricatorEnv::getEnvConfig('files.image-mime-types');
    $mime_type = $this->getMimeType();
    return idx($mime_map, $mime_type);
  }

  public function isTransformableImage() {

    // NOTE: The way the 'gd' extension works in PHP is that you can install it
    // with support for only some file types, so it might be able to handle
    // PNG but not JPEG. Try to generate thumbnails for whatever we can. Setup
    // warns you if you don't have complete support.

    $matches = null;
    $ok = preg_match(
      '@^image/(gif|png|jpe?g)@',
      $this->getViewableMimeType(),
      $matches);
    if (!$ok) {
      return false;
    }

    switch ($matches[1]) {
      case 'jpg';
      case 'jpeg':
        return function_exists('imagejpeg');
        break;
      case 'png':
        return function_exists('imagepng');
        break;
      case 'gif':
        return function_exists('imagegif');
        break;
      default:
        throw new Exception('Unknown type matched as image MIME type.');
    }
  }

  public static function getTransformableImageFormats() {
    $supported = array();

    if (function_exists('imagejpeg')) {
      $supported[] = 'jpg';
    }

    if (function_exists('imagepng')) {
      $supported[] = 'png';
    }

    if (function_exists('imagegif')) {
      $supported[] = 'gif';
    }

    return $supported;
  }

  protected function instantiateStorageEngine() {
    return self::buildEngine($this->getStorageEngine());
  }

  public static function buildEngine($engine_identifier) {
    $engines = self::buildAllEngines();
    foreach ($engines as $engine) {
      if ($engine->getEngineIdentifier() == $engine_identifier) {
        return $engine;
      }
    }

    throw new Exception(
      "Storage engine '{$engine_identifier}' could not be located!");
  }

  public static function buildAllEngines() {
    $engines = id(new PhutilSymbolLoader())
      ->setType('class')
      ->setConcreteOnly(true)
      ->setAncestorClass('PhabricatorFileStorageEngine')
      ->selectAndLoadSymbols();

    $results = array();
    foreach ($engines as $engine_class) {
      $results[] = newv($engine_class['name'], array());
    }

    return $results;
  }

  public function getViewableMimeType() {
    $mime_map = PhabricatorEnv::getEnvConfig('files.viewable-mime-types');

    $mime_type = $this->getMimeType();
    $mime_parts = explode(';', $mime_type);
    $mime_type = trim(reset($mime_parts));

    return idx($mime_map, $mime_type);
  }

  public function getDisplayIconForMimeType() {
    $mime_map = PhabricatorEnv::getEnvConfig('files.icon-mime-types');
    $mime_type = $this->getMimeType();
    return idx($mime_map, $mime_type, 'docs_file');
  }

  public function validateSecretKey($key) {
    return ($key == $this->getSecretKey());
  }

  public function save() {
    if (!$this->getSecretKey()) {
      $this->setSecretKey($this->generateSecretKey());
    }
    return parent::save();
  }

  public function generateSecretKey() {
    return Filesystem::readRandomCharacters(20);
  }

  public function updateDimensions($save = true) {
    if (!$this->isViewableImage()) {
      throw new Exception(
        "This file is not a viewable image.");
    }

    if (!function_exists("imagecreatefromstring")) {
      throw new Exception(
        "Cannot retrieve image information.");
    }

    $data = $this->loadFileData();

    $img = imagecreatefromstring($data);
    if ($img === false) {
      throw new Exception(
        "Error when decoding image.");
    }

    $this->metadata[self::METADATA_IMAGE_WIDTH] = imagesx($img);
    $this->metadata[self::METADATA_IMAGE_HEIGHT] = imagesy($img);

    if ($save) {
      $this->save();
    }

    return $this;
  }

  public static function getMetadataName($metadata) {
    switch ($metadata) {
      case self::METADATA_IMAGE_WIDTH:
        $name = pht('Width');
        break;
      case self::METADATA_IMAGE_HEIGHT:
        $name = pht('Height');
        break;
      default:
        $name = ucfirst($metadata);
        break;
    }

    return $name;
  }


/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // TODO: Implement proper per-object policies.
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}

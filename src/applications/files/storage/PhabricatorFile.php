<?php

/**
 * Parameters
 * ==========
 *
 * When creating a new file using a method like @{method:newFromFileData}, these
 * parameters are supported:
 *
 *   | name | Human readable filename.
 *   | authorPHID | User PHID of uploader.
 *   | ttl | Temporary file lifetime, in seconds.
 *   | viewPolicy | File visibility policy.
 *   | isExplicitUpload | Used to show users files they explicitly uploaded.
 *   | canCDN | Allows the file to be cached and delivered over a CDN.
 *   | mime-type | Optional, explicit file MIME type.
 *   | builtin | Optional filename, identifies this as a builtin.
 *
 */
final class PhabricatorFile extends PhabricatorFileDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorSubscribableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  const METADATA_IMAGE_WIDTH  = 'width';
  const METADATA_IMAGE_HEIGHT = 'height';
  const METADATA_CAN_CDN = 'canCDN';
  const METADATA_BUILTIN = 'builtin';
  const METADATA_PARTIAL = 'partial';
  const METADATA_PROFILE = 'profile';
  const METADATA_STORAGE = 'storage';

  protected $name;
  protected $mimeType;
  protected $byteSize;
  protected $authorPHID;
  protected $secretKey;
  protected $contentHash;
  protected $metadata = array();
  protected $mailKey;

  protected $storageEngine;
  protected $storageFormat;
  protected $storageHandle;

  protected $ttl;
  protected $isExplicitUpload = 1;
  protected $viewPolicy = PhabricatorPolicies::POLICY_USER;
  protected $isPartial = 0;

  private $objects = self::ATTACHABLE;
  private $objectPHIDs = self::ATTACHABLE;
  private $originalFile = self::ATTACHABLE;
  private $transforms = self::ATTACHABLE;

  public static function initializeNewFile() {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withClasses(array('PhabricatorFilesApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      FilesDefaultViewCapability::CAPABILITY);

    return id(new PhabricatorFile())
      ->setViewPolicy($view_policy)
      ->setIsPartial(0)
      ->attachOriginalFile(null)
      ->attachObjects(array())
      ->attachObjectPHIDs(array());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255?',
        'mimeType' => 'text255?',
        'byteSize' => 'uint64',
        'storageEngine' => 'text32',
        'storageFormat' => 'text32',
        'storageHandle' => 'text255',
        'authorPHID' => 'phid?',
        'secretKey' => 'bytes20?',
        'contentHash' => 'bytes40?',
        'ttl' => 'epoch?',
        'isExplicitUpload' => 'bool?',
        'mailKey' => 'bytes20',
        'isPartial' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
        'contentHash' => array(
          'columns' => array('contentHash'),
        ),
        'key_ttl' => array(
          'columns' => array('ttl'),
        ),
        'key_dateCreated' => array(
          'columns' => array('dateCreated'),
        ),
        'key_partial' => array(
          'columns' => array('authorPHID', 'isPartial'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorFileFilePHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->getSecretKey()) {
      $this->setSecretKey($this->generateSecretKey());
    }
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getMonogram() {
    return 'F'.$this->getID();
  }

  public function scrambleSecret() {
    return $this->setSecretKey($this->generateSecretKey());
  }

  public static function readUploadedFileData($spec) {
    if (!$spec) {
      throw new Exception(pht('No file was uploaded!'));
    }

    $err = idx($spec, 'error');
    if ($err) {
      throw new PhabricatorFileUploadException($err);
    }

    $tmp_name = idx($spec, 'tmp_name');
    $is_valid = @is_uploaded_file($tmp_name);
    if (!$is_valid) {
      throw new Exception(pht('File is not an uploaded file.'));
    }

    $file_data = Filesystem::readFile($tmp_name);
    $file_size = idx($spec, 'size');

    if (strlen($file_data) != $file_size) {
      throw new Exception(pht('File size disagrees with uploaded size.'));
    }

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
    return self::newFromFileData($data, $params);
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
      idx($params, 'name'),
      self::hashFileContent($data));

    if (!$file) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file = self::newFromFileData($data, $params);
      unset($unguarded);
    }

    return $file;
  }

  public static function newFileFromContentHash($hash, array $params) {
    // Check to see if a file with same contentHash exist
    $file = id(new PhabricatorFile())->loadOneWhere(
      'contentHash = %s LIMIT 1',
      $hash);

    if ($file) {
      $copy_of_storage_engine = $file->getStorageEngine();
      $copy_of_storage_handle = $file->getStorageHandle();
      $copy_of_storage_format = $file->getStorageFormat();
      $copy_of_storage_properties = $file->getStorageProperties();
      $copy_of_byte_size = $file->getByteSize();
      $copy_of_mime_type = $file->getMimeType();

      $new_file = self::initializeNewFile();

      $new_file->setByteSize($copy_of_byte_size);

      $new_file->setContentHash($hash);
      $new_file->setStorageEngine($copy_of_storage_engine);
      $new_file->setStorageHandle($copy_of_storage_handle);
      $new_file->setStorageFormat($copy_of_storage_format);
      $new_file->setStorageProperties($copy_of_storage_properties);
      $new_file->setMimeType($copy_of_mime_type);
      $new_file->copyDimensions($file);

      $new_file->readPropertiesFromParameters($params);

      $new_file->save();

      return $new_file;
    }

    return $file;
  }

  public static function newChunkedFile(
    PhabricatorFileStorageEngine $engine,
    $length,
    array $params) {

    $file = self::initializeNewFile();

    $file->setByteSize($length);

    // TODO: We might be able to test the first chunk in order to figure
    // this out more reliably, since MIME detection usually examines headers.
    // However, enormous files are probably always either actually raw data
    // or reasonable to treat like raw data.
    $file->setMimeType('application/octet-stream');

    $chunked_hash = idx($params, 'chunkedHash');
    if ($chunked_hash) {
      $file->setContentHash($chunked_hash);
    } else {
      // See PhabricatorChunkedFileStorageEngine::getChunkedHash() for some
      // discussion of this.
      $seed = Filesystem::readRandomBytes(64);
      $hash = PhabricatorChunkedFileStorageEngine::getChunkedHashForInput(
        $seed);
      $file->setContentHash($hash);
    }

    $file->setStorageEngine($engine->getEngineIdentifier());
    $file->setStorageHandle(PhabricatorFileChunk::newChunkHandle());

    // Chunked files are always stored raw because they do not actually store
    // data. The chunks do, and can be individually formatted.
    $file->setStorageFormat(PhabricatorFileRawStorageFormat::FORMATKEY);

    $file->setIsPartial(1);

    $file->readPropertiesFromParameters($params);

    return $file;
  }

  private static function buildFromFileData($data, array $params = array()) {

    if (isset($params['storageEngines'])) {
      $engines = $params['storageEngines'];
    } else {
      $size = strlen($data);
      $engines = PhabricatorFileStorageEngine::loadStorageEngines($size);

      if (!$engines) {
        throw new Exception(
          pht(
            'No configured storage engine can store this file. See '.
            '"Configuring File Storage" in the documentation for '.
            'information on configuring storage engines.'));
      }
    }

    assert_instances_of($engines, 'PhabricatorFileStorageEngine');
    if (!$engines) {
      throw new Exception(pht('No valid storage engines are available!'));
    }

    $file = self::initializeNewFile();

    $aes_type = PhabricatorFileAES256StorageFormat::FORMATKEY;
    $has_aes = PhabricatorKeyring::getDefaultKeyName($aes_type);
    if ($has_aes !== null) {
      $default_key = PhabricatorFileAES256StorageFormat::FORMATKEY;
    } else {
      $default_key = PhabricatorFileRawStorageFormat::FORMATKEY;
    }
    $key = idx($params, 'format', $default_key);

    // Callers can pass in an object explicitly instead of a key. This is
    // primarily useful for unit tests.
    if ($key instanceof PhabricatorFileStorageFormat) {
      $format = clone $key;
    } else {
      $format = clone PhabricatorFileStorageFormat::requireFormat($key);
    }

    $format->setFile($file);

    $properties = $format->newStorageProperties();
    $file->setStorageFormat($format->getStorageFormatKey());
    $file->setStorageProperties($properties);

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
        pht('All storage engines failed to write file:'),
        $exceptions);
    }

    $file->setByteSize(strlen($data));
    $file->setContentHash(self::hashFileContent($data));

    $file->setStorageEngine($engine_identifier);
    $file->setStorageHandle($data_handle);

    $file->readPropertiesFromParameters($params);

    if (!$file->getMimeType()) {
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
        pht("You can not migrate a file which hasn't yet been saved."));
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
    $old_identifier = $this->getStorageEngine();
    $old_handle = $this->getStorageHandle();

    $this->setStorageEngine($new_identifier);
    $this->setStorageHandle($new_handle);
    $this->save();

    $this->deleteFileDataIfUnused(
      $old_engine,
      $old_identifier,
      $old_handle);

    return $this;
  }

  public function migrateToStorageFormat(PhabricatorFileStorageFormat $format) {
    if (!$this->getID() || !$this->getStorageHandle()) {
      throw new Exception(
        pht("You can not migrate a file which hasn't yet been saved."));
    }

    $data = $this->loadFileData();
    $params = array(
      'name' => $this->getName(),
    );

    $engine = $this->instantiateStorageEngine();
    $old_handle = $this->getStorageHandle();

    $properties = $format->newStorageProperties();
    $this->setStorageFormat($format->getStorageFormatKey());
    $this->setStorageProperties($properties);

    list($identifier, $new_handle) = $this->writeToEngine(
      $engine,
      $data,
      $params);

    $this->setStorageHandle($new_handle);
    $this->save();

    $this->deleteFileDataIfUnused(
      $engine,
      $identifier,
      $old_handle);

    return $this;
  }

  public function cycleMasterStorageKey(PhabricatorFileStorageFormat $format) {
    if (!$this->getID() || !$this->getStorageHandle()) {
      throw new Exception(
        pht("You can not cycle keys for a file which hasn't yet been saved."));
    }

    $properties = $format->cycleStorageProperties();
    $this->setStorageProperties($properties);
    $this->save();

    return $this;
  }

  private function writeToEngine(
    PhabricatorFileStorageEngine $engine,
    $data,
    array $params) {

    $engine_class = get_class($engine);

    $key = $this->getStorageFormat();
    $format = id(clone PhabricatorFileStorageFormat::requireFormat($key))
      ->setFile($this);

    $data_iterator = array($data);
    $formatted_iterator = $format->newWriteIterator($data_iterator);
    $formatted_data = $this->loadDataFromIterator($formatted_iterator);

    $data_handle = $engine->writeFile($formatted_data, $params);

    if (!$data_handle || strlen($data_handle) > 255) {
      // This indicates an improperly implemented storage engine.
      throw new PhabricatorFileStorageConfigurationException(
        pht(
          "Storage engine '%s' executed %s but did not return a valid ".
          "handle ('%s') to the data: it must be nonempty and no longer ".
          "than 255 characters.",
          $engine_class,
          'writeFile()',
          $data_handle));
    }

    $engine_identifier = $engine->getEngineIdentifier();
    if (!$engine_identifier || strlen($engine_identifier) > 32) {
      throw new PhabricatorFileStorageConfigurationException(
        pht(
          "Storage engine '%s' returned an improper engine identifier '{%s}': ".
          "it must be nonempty and no longer than 32 characters.",
          $engine_class,
          $engine_identifier));
    }

    return array($engine_identifier, $data_handle);
  }


  /**
   * Download a remote resource over HTTP and save the response body as a file.
   *
   * This method respects `security.outbound-blacklist`, and protects against
   * HTTP redirection (by manually following "Location" headers and verifying
   * each destination). It does not protect against DNS rebinding. See
   * discussion in T6755.
   */
  public static function newFromFileDownload($uri, array $params = array()) {
    $timeout = 5;

    $redirects = array();
    $current = $uri;
    while (true) {
      try {
        if (count($redirects) > 10) {
          throw new Exception(
            pht('Too many redirects trying to fetch remote URI.'));
        }

        $resolved = PhabricatorEnv::requireValidRemoteURIForFetch(
          $current,
          array(
            'http',
            'https',
          ));

        list($resolved_uri, $resolved_domain) = $resolved;

        $current = new PhutilURI($current);
        if ($current->getProtocol() == 'http') {
          // For HTTP, we can use a pre-resolved URI to defuse DNS rebinding.
          $fetch_uri = $resolved_uri;
          $fetch_host = $resolved_domain;
        } else {
          // For HTTPS, we can't: cURL won't verify the SSL certificate if
          // the domain has been replaced with an IP. But internal services
          // presumably will not have valid certificates for rebindable
          // domain names on attacker-controlled domains, so the DNS rebinding
          // attack should generally not be possible anyway.
          $fetch_uri = $current;
          $fetch_host = null;
        }

        $future = id(new HTTPSFuture($fetch_uri))
          ->setFollowLocation(false)
          ->setTimeout($timeout);

        if ($fetch_host !== null) {
          $future->addHeader('Host', $fetch_host);
        }

        list($status, $body, $headers) = $future->resolve();

        if ($status->isRedirect()) {
          // This is an HTTP 3XX status, so look for a "Location" header.
          $location = null;
          foreach ($headers as $header) {
            list($name, $value) = $header;
            if (phutil_utf8_strtolower($name) == 'location') {
              $location = $value;
              break;
            }
          }

          // HTTP 3XX status with no "Location" header, just treat this like
          // a normal HTTP error.
          if ($location === null) {
            throw $status;
          }

          if (isset($redirects[$location])) {
            throw new Exception(
              pht('Encountered loop while following redirects.'));
          }

          $redirects[$location] = $location;
          $current = $location;
          // We'll fall off the bottom and go try this URI now.
        } else if ($status->isError()) {
          // This is something other than an HTTP 2XX or HTTP 3XX status, so
          // just bail out.
          throw $status;
        } else {
          // This is HTTP 2XX, so use the response body to save the
          // file data.
          $params = $params + array(
            'name' => basename($uri),
          );

          return self::newFromFileData($body, $params);
        }
      } catch (Exception $ex) {
        if ($redirects) {
          throw new PhutilProxyException(
            pht(
              'Failed to fetch remote URI "%s" after following %s redirect(s) '.
              '(%s): %s',
              $uri,
              phutil_count($redirects),
              implode(' > ', array_keys($redirects)),
              $ex->getMessage()),
            $ex);
        } else {
          throw $ex;
        }
      }
    }
  }

  public static function normalizeFileName($file_name) {
    $pattern = "@[\\x00-\\x19#%&+!~'\$\"\/=\\\\?<> ]+@";
    $file_name = preg_replace($pattern, '_', $file_name);
    $file_name = preg_replace('@_+@', '_', $file_name);
    $file_name = trim($file_name, '_');

    $disallowed_filenames = array(
      '.'  => 'dot',
      '..' => 'dotdot',
      ''   => 'file',
    );
    $file_name = idx($disallowed_filenames, $file_name, $file_name);

    return $file_name;
  }

  public function delete() {
    // We want to delete all the rows which mark this file as the transformation
    // of some other file (since we're getting rid of it). We also delete all
    // the transformations of this file, so that a user who deletes an image
    // doesn't need to separately hunt down and delete a bunch of thumbnails and
    // resizes of it.

    $outbound_xforms = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withTransforms(
        array(
          array(
            'originalPHID' => $this->getPHID(),
            'transform'    => true,
          ),
        ))
      ->execute();

    foreach ($outbound_xforms as $outbound_xform) {
      $outbound_xform->delete();
    }

    $inbound_xforms = id(new PhabricatorTransformedFile())->loadAllWhere(
      'transformedPHID = %s',
      $this->getPHID());

    $this->openTransaction();
      foreach ($inbound_xforms as $inbound_xform) {
        $inbound_xform->delete();
      }
      $ret = parent::delete();
    $this->saveTransaction();

    $this->deleteFileDataIfUnused(
      $this->instantiateStorageEngine(),
      $this->getStorageEngine(),
      $this->getStorageHandle());

    return $ret;
  }


  /**
   * Destroy stored file data if there are no remaining files which reference
   * it.
   */
  public function deleteFileDataIfUnused(
    PhabricatorFileStorageEngine $engine,
    $engine_identifier,
    $handle) {

    // Check to see if any files are using storage.
    $usage = id(new PhabricatorFile())->loadAllWhere(
      'storageEngine = %s AND storageHandle = %s LIMIT 1',
      $engine_identifier,
      $handle);

    // If there are no files using the storage, destroy the actual storage.
    if (!$usage) {
      try {
        $engine->deleteFile($handle);
      } catch (Exception $ex) {
        // In the worst case, we're leaving some data stranded in a storage
        // engine, which is not a big deal.
        phlog($ex);
      }
    }
  }


  public static function hashFileContent($data) {
    return sha1($data);
  }

  public function loadFileData() {
    $iterator = $this->getFileDataIterator();
    return $this->loadDataFromIterator($iterator);
  }


  /**
   * Return an iterable which emits file content bytes.
   *
   * @param int Offset for the start of data.
   * @param int Offset for the end of data.
   * @return Iterable Iterable object which emits requested data.
   */
  public function getFileDataIterator($begin = null, $end = null) {
    $engine = $this->instantiateStorageEngine();
    $raw_iterator = $engine->getRawFileDataIterator($this, $begin, $end);

    $key = $this->getStorageFormat();

    $format = id(clone PhabricatorFileStorageFormat::requireFormat($key))
      ->setFile($this);

    return $format->newReadIterator($raw_iterator);
  }


  public function getViewURI() {
    if (!$this->getPHID()) {
      throw new Exception(
        pht('You must save a file before you can generate a view URI.'));
    }

    return $this->getCDNURI();
  }

  public function getCDNURI() {
    $name = self::normalizeFileName($this->getName());
    $name = phutil_escape_uri($name);

    $parts = array();
    $parts[] = 'file';
    $parts[] = 'data';

    // If this is an instanced install, add the instance identifier to the URI.
    // Instanced configurations behind a CDN may not be able to control the
    // request domain used by the CDN (as with AWS CloudFront). Embedding the
    // instance identity in the path allows us to distinguish between requests
    // originating from different instances but served through the same CDN.
    $instance = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($instance)) {
      $parts[] = '@'.$instance;
    }

    $parts[] = $this->getSecretKey();
    $parts[] = $this->getPHID();
    $parts[] = $name;

    $path = '/'.implode('/', $parts);

    // If this file is only partially uploaded, we're just going to return a
    // local URI to make sure that Ajax works, since the page is inevitably
    // going to give us an error back.
    if ($this->getIsPartial()) {
      return PhabricatorEnv::getURI($path);
    } else {
      return PhabricatorEnv::getCDNURI($path);
    }
  }


  public function getInfoURI() {
    return '/'.$this->getMonogram();
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
    return (string)$uri;
  }

  public function getURIForTransform(PhabricatorFileTransform $transform) {
    return $this->getTransformedURI($transform->getTransformKey());
  }

  private function getTransformedURI($transform) {
    $parts = array();
    $parts[] = 'file';
    $parts[] = 'xform';

    $instance = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($instance)) {
      $parts[] = '@'.$instance;
    }

    $parts[] = $transform;
    $parts[] = $this->getPHID();
    $parts[] = $this->getSecretKey();

    $path = implode('/', $parts);
    $path = $path.'/';

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

  public function isAudio() {
    if (!$this->isViewableInBrowser()) {
      return false;
    }

    $mime_map = PhabricatorEnv::getEnvConfig('files.audio-mime-types');
    $mime_type = $this->getMimeType();
    return idx($mime_map, $mime_type);
  }

  public function isVideo() {
    if (!$this->isViewableInBrowser()) {
      return false;
    }

    $mime_map = PhabricatorEnv::getEnvConfig('files.video-mime-types');
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
        throw new Exception(pht('Unknown type matched as image MIME type.'));
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

  public function getDragAndDropDictionary() {
    return array(
      'id'   => $this->getID(),
      'phid' => $this->getPHID(),
      'uri'  => $this->getBestURI(),
    );
  }

  public function instantiateStorageEngine() {
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
      pht(
        "Storage engine '%s' could not be located!",
        $engine_identifier));
  }

  public static function buildAllEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFileStorageEngine')
      ->execute();
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
    return idx($mime_map, $mime_type, 'fa-file-o');
  }

  public function validateSecretKey($key) {
    return ($key == $this->getSecretKey());
  }

  public function generateSecretKey() {
    return Filesystem::readRandomCharacters(20);
  }

  public function setStorageProperties(array $properties) {
    $this->metadata[self::METADATA_STORAGE] = $properties;
    return $this;
  }

  public function getStorageProperties() {
    return idx($this->metadata, self::METADATA_STORAGE, array());
  }

  public function getStorageProperty($key, $default = null) {
    $properties = $this->getStorageProperties();
    return idx($properties, $key, $default);
  }

  public function loadDataFromIterator($iterator) {
    $result = '';

    foreach ($iterator as $chunk) {
      $result .= $chunk;
    }

    return $result;
  }

  public function updateDimensions($save = true) {
    if (!$this->isViewableImage()) {
      throw new Exception(pht('This file is not a viewable image.'));
    }

    if (!function_exists('imagecreatefromstring')) {
      throw new Exception(pht('Cannot retrieve image information.'));
    }

    $data = $this->loadFileData();

    $img = imagecreatefromstring($data);
    if ($img === false) {
      throw new Exception(pht('Error when decoding image.'));
    }

    $this->metadata[self::METADATA_IMAGE_WIDTH] = imagesx($img);
    $this->metadata[self::METADATA_IMAGE_HEIGHT] = imagesy($img);

    if ($save) {
      $this->save();
    }

    return $this;
  }

  public function copyDimensions(PhabricatorFile $file) {
    $metadata = $file->getMetadata();
    $width = idx($metadata, self::METADATA_IMAGE_WIDTH);
    if ($width) {
      $this->metadata[self::METADATA_IMAGE_WIDTH] = $width;
    }
    $height = idx($metadata, self::METADATA_IMAGE_HEIGHT);
    if ($height) {
      $this->metadata[self::METADATA_IMAGE_HEIGHT] = $height;
    }

    return $this;
  }


  /**
   * Load (or build) the {@class:PhabricatorFile} objects for builtin file
   * resources. The builtin mechanism allows files shipped with Phabricator
   * to be treated like normal files so that APIs do not need to special case
   * things like default images or deleted files.
   *
   * Builtins are located in `resources/builtin/` and identified by their
   * name.
   *
   * @param  PhabricatorUser Viewing user.
   * @param  list<PhabricatorFilesBuiltinFile> List of builtin file specs.
   * @return dict<string, PhabricatorFile> Dictionary of named builtins.
   */
  public static function loadBuiltins(PhabricatorUser $user, array $builtins) {
    $builtins = mpull($builtins, null, 'getBuiltinFileKey');

    $specs = array();
    foreach ($builtins as $key => $buitin) {
      $specs[] = array(
        'originalPHID' => PhabricatorPHIDConstants::PHID_VOID,
        'transform'    => $key,
      );
    }

    // NOTE: Anyone is allowed to access builtin files.

    $files = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withTransforms($specs)
      ->execute();

    $results = array();
    foreach ($files as $file) {
      $builtin_key = $file->getBuiltinName();
      if ($builtin_key !== null) {
        $results[$builtin_key] = $file;
      }
    }

    $build = array();
    foreach ($builtins as $key => $builtin) {
      if (isset($results[$key])) {
        continue;
      }

      $data = $builtin->loadBuiltinFileData();

      $params = array(
        'name' => $builtin->getBuiltinDisplayName(),
        'ttl'  => time() + (60 * 60 * 24 * 7),
        'canCDN' => true,
        'builtin' => $key,
      );

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $file = self::newFromFileData($data, $params);
        $xform = id(new PhabricatorTransformedFile())
          ->setOriginalPHID(PhabricatorPHIDConstants::PHID_VOID)
          ->setTransform($key)
          ->setTransformedPHID($file->getPHID())
          ->save();
      unset($unguarded);

      $file->attachObjectPHIDs(array());
      $file->attachObjects(array());

      $results[$key] = $file;
    }

    return $results;
  }


  /**
   * Convenience wrapper for @{method:loadBuiltins}.
   *
   * @param PhabricatorUser   Viewing user.
   * @param string            Single builtin name to load.
   * @return PhabricatorFile  Corresponding builtin file.
   */
  public static function loadBuiltin(PhabricatorUser $user, $name) {
    $builtin = id(new PhabricatorFilesOnDiskBuiltinFile())
      ->setName($name);

    $key = $builtin->getBuiltinFileKey();

    return idx(self::loadBuiltins($user, array($builtin)), $key);
  }

  public function getObjects() {
    return $this->assertAttached($this->objects);
  }

  public function attachObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  public function getObjectPHIDs() {
    return $this->assertAttached($this->objectPHIDs);
  }

  public function attachObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function getOriginalFile() {
    return $this->assertAttached($this->originalFile);
  }

  public function attachOriginalFile(PhabricatorFile $file = null) {
    $this->originalFile = $file;
    return $this;
  }

  public function getImageHeight() {
    if (!$this->isViewableImage()) {
      return null;
    }
    return idx($this->metadata, self::METADATA_IMAGE_HEIGHT);
  }

  public function getImageWidth() {
    if (!$this->isViewableImage()) {
      return null;
    }
    return idx($this->metadata, self::METADATA_IMAGE_WIDTH);
  }

  public function getCanCDN() {
    if (!$this->isViewableImage()) {
      return false;
    }

    return idx($this->metadata, self::METADATA_CAN_CDN);
  }

  public function setCanCDN($can_cdn) {
    $this->metadata[self::METADATA_CAN_CDN] = $can_cdn ? 1 : 0;
    return $this;
  }

  public function isBuiltin() {
    return ($this->getBuiltinName() !== null);
  }

  public function getBuiltinName() {
    return idx($this->metadata, self::METADATA_BUILTIN);
  }

  public function setBuiltinName($name) {
    $this->metadata[self::METADATA_BUILTIN] = $name;
    return $this;
  }

  public function getIsProfileImage() {
    return idx($this->metadata, self::METADATA_PROFILE);
  }

  public function setIsProfileImage($value) {
    $this->metadata[self::METADATA_PROFILE] = $value;
    return $this;
  }

  /**
   * Write the policy edge between this file and some object.
   *
   * @param phid Object PHID to attach to.
   * @return this
   */
  public function attachToObject($phid) {
    $edge_type = PhabricatorObjectHasFileEdgeType::EDGECONST;

    id(new PhabricatorEdgeEditor())
      ->addEdge($phid, $edge_type, $this->getPHID())
      ->save();

    return $this;
  }


  /**
   * Remove the policy edge between this file and some object.
   *
   * @param phid Object PHID to detach from.
   * @return this
   */
  public function detachFromObject($phid) {
    $edge_type = PhabricatorObjectHasFileEdgeType::EDGECONST;

    id(new PhabricatorEdgeEditor())
      ->removeEdge($phid, $edge_type, $this->getPHID())
      ->save();

    return $this;
  }


  /**
   * Configure a newly created file object according to specified parameters.
   *
   * This method is called both when creating a file from fresh data, and
   * when creating a new file which reuses existing storage.
   *
   * @param map<string, wild>   Bag of parameters, see @{class:PhabricatorFile}
   *  for documentation.
   * @return this
   */
  private function readPropertiesFromParameters(array $params) {
    $file_name = idx($params, 'name');
    $this->setName($file_name);

    $author_phid = idx($params, 'authorPHID');
    $this->setAuthorPHID($author_phid);

    $file_ttl = idx($params, 'ttl');
    $this->setTtl($file_ttl);

    $view_policy = idx($params, 'viewPolicy');
    if ($view_policy) {
      $this->setViewPolicy($params['viewPolicy']);
    }

    $is_explicit = (idx($params, 'isExplicitUpload') ? 1 : 0);
    $this->setIsExplicitUpload($is_explicit);

    $can_cdn = idx($params, 'canCDN');
    if ($can_cdn) {
      $this->setCanCDN(true);
    }

    $builtin = idx($params, 'builtin');
    if ($builtin) {
      $this->setBuiltinName($builtin);
    }

    $profile = idx($params, 'profile');
    if ($profile) {
      $this->setIsProfileImage(true);
    }

    $mime_type = idx($params, 'mime-type');
    if ($mime_type) {
      $this->setMimeType($mime_type);
    }

    return $this;
  }

  public function getRedirectResponse() {
    $uri = $this->getBestURI();

    // TODO: This is a bit iffy. Sometimes, getBestURI() returns a CDN URI
    // (if the file is a viewable image) and sometimes a local URI (if not).
    // For now, just detect which one we got and configure the response
    // appropriately. In the long run, if this endpoint is served from a CDN
    // domain, we can't issue a local redirect to an info URI (which is not
    // present on the CDN domain). We probably never actually issue local
    // redirects here anyway, since we only ever transform viewable images
    // right now.

    $is_external = strlen(id(new PhutilURI($uri))->getDomain());

    return id(new AphrontRedirectResponse())
      ->setIsExternal($is_external)
      ->setURI($uri);
  }

  public function attachTransforms(array $map) {
    $this->transforms = $map;
    return $this;
  }

  public function getTransform($key) {
    return $this->assertAttachedKey($this->transforms, $key);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorFileEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorFileTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if ($this->isBuiltin()) {
          return PhabricatorPolicies::getMostOpenPolicy();
        }
        if ($this->getIsProfileImage()) {
          return PhabricatorPolicies::getMostOpenPolicy();
        }
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $viewer_phid = $viewer->getPHID();
    if ($viewer_phid) {
      if ($this->getAuthorPHID() == $viewer_phid) {
        return true;
      }
    }

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        // If you can see the file this file is a transform of, you can see
        // this file.
        if ($this->getOriginalFile()) {
          return true;
        }

        // If you can see any object this file is attached to, you can see
        // the file.
        return (count($this->getObjects()) > 0);
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    $out = array();
    $out[] = pht('The user who uploaded a file can always view and edit it.');
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $out[] = pht(
          'Files attached to objects are visible to users who can view '.
          'those objects.');
        $out[] = pht(
          'Thumbnails are visible only to users who can view the original '.
          'file.');
        break;
    }

    return $out;
  }


/* -(  PhabricatorSubscribableInterface Implementation  )-------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($this->authorPHID == $phid);
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }

}

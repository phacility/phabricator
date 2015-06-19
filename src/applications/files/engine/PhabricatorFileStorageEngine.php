<?php

/**
 * Defines a storage engine which can write file data somewhere (like a
 * database, local disk, Amazon S3, the A:\ drive, or a custom filer) and
 * retrieve it later.
 *
 * You can extend this class to provide new file storage backends.
 *
 * For more information, see @{article:File Storage Technical Documentation}.
 *
 * @task construct Constructing an Engine
 * @task meta Engine Metadata
 * @task file Managing File Data
 * @task load Loading Storage Engines
 */
abstract class PhabricatorFileStorageEngine extends Phobject {

  /**
   * Construct a new storage engine.
   *
   * @task construct
   */
  final public function __construct() {
    // <empty>
  }


/* -(  Engine Metadata  )---------------------------------------------------- */


  /**
   * Return a unique, nonempty string which identifies this storage engine.
   * This is used to look up the storage engine when files needs to be read or
   * deleted. For instance, if you store files by giving them to a duck for
   * safe keeping in his nest down by the pond, you might return 'duck' from
   * this method.
   *
   * @return string Unique string for this engine, max length 32.
   * @task meta
   */
  abstract public function getEngineIdentifier();


  /**
   * Prioritize this engine relative to other engines.
   *
   * Engines with a smaller priority number get an opportunity to write files
   * first. Generally, lower-latency filestores should have lower priority
   * numbers, and higher-latency filestores should have higher priority
   * numbers. Setting priority to approximately the number of milliseconds of
   * read latency will generally produce reasonable results.
   *
   * In conjunction with filesize limits, the goal is to store small files like
   * profile images, thumbnails, and text snippets in lower-latency engines,
   * and store large files in higher-capacity engines.
   *
   * @return float Engine priority.
   * @task meta
   */
  abstract public function getEnginePriority();


  /**
   * Return `true` if the engine is currently writable.
   *
   * Engines that are disabled or missing configuration should return `false`
   * to prevent new writes. If writes were made with this engine in the past,
   * the application may still try to perform reads.
   *
   * @return bool True if this engine can support new writes.
   * @task meta
   */
  abstract public function canWriteFiles();


  /**
   * Return `true` if the engine has a filesize limit on storable files.
   *
   * The @{method:getFilesizeLimit} method can retrieve the actual limit. This
   * method just removes the ambiguity around the meaning of a `0` limit.
   *
   * @return bool `true` if the engine has a filesize limit.
   * @task meta
   */
  public function hasFilesizeLimit() {
    return true;
  }


  /**
   * Return maximum storable file size, in bytes.
   *
   * Not all engines have a limit; use @{method:getFilesizeLimit} to check if
   * an engine has a limit. Engines without a limit can store files of any
   * size.
   *
   * By default, engines define a limit which supports chunked storage of
   * large files. In most cases, you should not change this limit, even if an
   * engine has vast storage capacity: chunked storage makes large files more
   * manageable and enables features like resumable uploads.
   *
   * @return int Maximum storable file size, in bytes.
   * @task meta
   */
  public function getFilesizeLimit() {
    // NOTE: This 8MB limit is selected to be larger than the 4MB chunk size,
    // but not much larger. Files between 0MB and 8MB will be stored normally;
    // files larger than 8MB will be chunked.
    return (1024 * 1024 * 8);
  }


  /**
   * Identifies storage engines that support unit tests.
   *
   * These engines are not used for production writes.
   *
   * @return bool True if this is a test engine.
   * @task meta
   */
  public function isTestEngine() {
    return false;
  }


  /**
   * Identifies chunking storage engines.
   *
   * If this is a storage engine which splits files into chunks and stores the
   * chunks in other engines, it can return `true` to signal that other
   * chunking engines should not try to store data here.
   *
   * @return bool True if this is a chunk engine.
   * @task meta
   */
  public function isChunkEngine() {
    return false;
  }


/* -(  Managing File Data  )------------------------------------------------- */


  /**
   * Write file data to the backing storage and return a handle which can later
   * be used to read or delete it. For example, if the backing storage is local
   * disk, the handle could be the path to the file.
   *
   * The caller will provide a $params array, which may be empty or may have
   * some metadata keys (like "name" and "author") in it. You should be prepared
   * to handle writes which specify no metadata, but might want to optionally
   * use some keys in this array for debugging or logging purposes. This is
   * the same dictionary passed to @{method:PhabricatorFile::newFromFileData},
   * so you could conceivably do custom things with it.
   *
   * If you are unable to write for whatever reason (e.g., the disk is full),
   * throw an exception. If there are other satisfactory but less-preferred
   * storage engines available, they will be tried.
   *
   * @param  string The file data to write.
   * @param  array  File metadata (name, author), if available.
   * @return string Unique string which identifies the stored file, max length
   *                255.
   * @task file
   */
  abstract public function writeFile($data, array $params);


  /**
   * Read the contents of a file previously written by @{method:writeFile}.
   *
   * @param   string  The handle returned from @{method:writeFile} when the
   *                  file was written.
   * @return  string  File contents.
   * @task file
   */
  abstract public function readFile($handle);


  /**
   * Delete the data for a file previously written by @{method:writeFile}.
   *
   * @param   string  The handle returned from @{method:writeFile} when the
   *                  file was written.
   * @return  void
   * @task file
   */
  abstract public function deleteFile($handle);



/* -(  Loading Storage Engines  )-------------------------------------------- */


  /**
   * Select viable default storage engines according to configuration. We'll
   * select the MySQL and Local Disk storage engines if they are configured
   * to allow a given file.
   *
   * @param int File size in bytes.
   * @task load
   */
  public static function loadStorageEngines($length) {
    $engines = self::loadWritableEngines();

    $writable = array();
    foreach ($engines as $key => $engine) {
      if ($engine->hasFilesizeLimit()) {
        $limit = $engine->getFilesizeLimit();
        if ($limit < $length) {
          continue;
        }
      }

      $writable[$key] = $engine;
    }

    return $writable;
  }


  /**
   * @task load
   */
  public static function loadAllEngines() {
    static $engines;

    if ($engines === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      foreach ($objects as $engine) {
        $key = $engine->getEngineIdentifier();
        if (empty($map[$key])) {
          $map[$key] = $engine;
        } else {
          throw new Exception(
            pht(
              'Storage engines "%s" and "%s" have the same engine '.
              'identifier "%s". Each storage engine must have a unique '.
              'identifier.',
              get_class($engine),
              get_class($map[$key]),
              $key));
        }
      }

      $map = msort($map, 'getEnginePriority');

      $engines = $map;
    }

    return $engines;
  }


  /**
   * @task load
   */
  private static function loadProductionEngines() {
    $engines = self::loadAllEngines();

    $active = array();
    foreach ($engines as $key => $engine) {
      if ($engine->isTestEngine()) {
        continue;
      }

      $active[$key] = $engine;
    }

    return $active;
  }


  /**
   * @task load
   */
  public static function loadWritableEngines() {
    $engines = self::loadProductionEngines();

    $writable = array();
    foreach ($engines as $key => $engine) {
      if (!$engine->canWriteFiles()) {
        continue;
      }

      if ($engine->isChunkEngine()) {
        // Don't select chunk engines as writable.
        continue;
      }
      $writable[$key] = $engine;
    }

    return $writable;
  }

  /**
   * @task load
   */
  public static function loadWritableChunkEngines() {
    $engines = self::loadProductionEngines();

    $chunk = array();
    foreach ($engines as $key => $engine) {
      if (!$engine->canWriteFiles()) {
        continue;
      }
      if (!$engine->isChunkEngine()) {
        continue;
      }
      $chunk[$key] = $engine;
    }

    return $chunk;
  }



  /**
   * Return the largest file size which can not be uploaded in chunks.
   *
   * Files smaller than this will always upload in one request, so clients
   * can safely skip the allocation step.
   *
   * @return int|null Byte size, or `null` if there is no chunk support.
   */
  public static function getChunkThreshold() {
    $engines = self::loadWritableChunkEngines();

    $min = null;
    foreach ($engines as $engine) {
      if (!$min) {
        $min = $engine;
        continue;
      }

      if ($min->getChunkSize() > $engine->getChunkSize()) {
        $min = $engine->getChunkSize();
      }
    }

    if (!$min) {
      return null;
    }

    return $engine->getChunkSize();
  }

  public function getFileDataIterator(PhabricatorFile $file, $begin, $end) {
    // The default implementation is trivial and just loads the entire file
    // upfront.
    $data = $file->loadFileData();

    if ($begin !== null && $end !== null) {
      $data = substr($data, $begin, ($end - $begin));
    } else if ($begin !== null) {
      $data = substr($data, $begin);
    } else if ($end !== null) {
      $data = substr($data, 0, $end);
    }

    return array($data);
  }

}

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
 * @task meta Engine Metadata
 * @task file Managing File Data
 * @group filestorage
 */
abstract class PhabricatorFileStorageEngine {

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

}

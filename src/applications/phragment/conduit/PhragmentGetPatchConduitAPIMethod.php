<?php

final class PhragmentGetPatchConduitAPIMethod
  extends PhragmentConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phragment.getpatch';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Retrieve the patches to apply for a given set of files.');
  }

  protected function defineParamTypes() {
    return array(
      'path' => 'required string',
      'state' => 'required dict<string, string>',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_FRAGMENT' => pht('No such fragment exists.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $path = $request->getValue('path');
    $state = $request->getValue('state');
    // The state is an array mapping file paths to hashes.

    $patches = array();

    // We need to get all of the mappings (like phragment.getstate) first
    // so that we can detect deletions and creations of files.
    $fragment = id(new PhragmentFragmentQuery())
      ->setViewer($request->getUser())
      ->withPaths(array($path))
      ->executeOne();
    if ($fragment === null) {
      throw new ConduitException('ERR_BAD_FRAGMENT');
    }

    $mappings = $fragment->getFragmentMappings(
      $request->getUser(),
      $fragment->getPath());

    $file_phids = mpull(mpull($mappings, 'getLatestVersion'), 'getFilePHID');
    $files = id(new PhabricatorFileQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($file_phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');

    // Scan all of the files that the caller currently has and iterate
    // over that.
    foreach ($state as $path => $hash) {
      // If $mappings[$path] exists, then the user has the file and it's
      // also a fragment.
      if (array_key_exists($path, $mappings)) {
        $file_phid = $mappings[$path]->getLatestVersion()->getFilePHID();
        if ($file_phid !== null) {
          // If the file PHID is present, then we need to check the
          // hashes to see if they are the same.
          $hash_caller = strtolower($state[$path]);
          $hash_current = $files[$file_phid]->getContentHash();
          if ($hash_caller === $hash_current) {
            // The user's version is identical to our version, so
            // there is no update needed.
          } else {
            // The hash differs, and the user needs to update.
            $patches[] = array(
              'path' => $path,
              'fileOld' => null,
              'fileNew' => $files[$file_phid],
              'hashOld' => $hash_caller,
              'hashNew' => $hash_current,
              'patchURI' => null,
            );
          }
        } else {
          // We have a record of this as a file, but there is no file
          // attached to the latest version, so we consider this to be
          // a deletion.
          $patches[] = array(
            'path' => $path,
            'fileOld' => null,
            'fileNew' => null,
            'hashOld' => $hash_caller,
            'hashNew' => PhragmentPatchUtil::EMPTY_HASH,
            'patchURI' => null,
          );
        }
      } else {
        // If $mappings[$path] does not exist, then the user has a file,
        // and we have absolutely no record of it what-so-ever (we haven't
        // even recorded a deletion). Assuming most applications will store
        // some form of data near their own files, this is probably a data
        // file relevant for the application that is not versioned, so we
        // don't tell the client to do anything with it.
      }
    }

    // Check the remaining files that we know about but the caller has
    // not reported.
    foreach ($mappings as $path => $child) {
      if (array_key_exists($path, $state)) {
        // We have already evaluated this above.
      } else {
        $file_phid = $mappings[$path]->getLatestVersion()->getFilePHID();
        if ($file_phid !== null) {
          // If the file PHID is present, then this is a new file that
          // we know about, but the caller does not. We need to tell
          // the caller to create the file.
          $hash_current = $files[$file_phid]->getContentHash();
          $patches[] = array(
            'path' => $path,
            'fileOld' => null,
            'fileNew' => $files[$file_phid],
            'hashOld' => PhragmentPatchUtil::EMPTY_HASH,
            'hashNew' => $hash_current,
            'patchURI' => null,
          );
        } else {
          // We have a record of deleting this file, and the caller hasn't
          // reported it, so they've probably deleted it in a previous
          // update.
        }
      }
    }

    // Before we can calculate patches, we need to resolve the old versions
    // of files so we can draw diffs on them.
    $hashes = array();
    foreach ($patches as $patch) {
      if ($patch['hashOld'] !== PhragmentPatchUtil::EMPTY_HASH) {
        $hashes[] = $patch['hashOld'];
      }
    }
    $old_files = array();
    if (count($hashes) !== 0) {
      $old_files = id(new PhabricatorFileQuery())
        ->setViewer($request->getUser())
        ->withContentHashes($hashes)
        ->execute();
    }
    $old_files = mpull($old_files, null, 'getContentHash');
    foreach ($patches as $key => $patch) {
      if ($patch['hashOld'] !== PhragmentPatchUtil::EMPTY_HASH) {
        if (array_key_exists($patch['hashOld'], $old_files)) {
          $patches[$key]['fileOld'] = $old_files[$patch['hashOld']];
        } else {
          // We either can't see or can't read the old file.
          $patches[$key]['hashOld'] = PhragmentPatchUtil::EMPTY_HASH;
          $patches[$key]['fileOld'] = null;
        }
      }
    }

    // Now run through all of the patch entries, calculate the patches
    // and return the results.
    foreach ($patches as $key => $patch) {
      $data = PhragmentPatchUtil::calculatePatch(
        $patches[$key]['fileOld'],
        $patches[$key]['fileNew']);
      unset($patches[$key]['fileOld']);
      unset($patches[$key]['fileNew']);

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $file = PhabricatorFile::newFromFileData(
          $data,
          array(
            'name' => 'patch.dmp',
            'ttl.relative' => phutil_units('24 hours in seconds'),
          ));
      unset($unguarded);

      $patches[$key]['patchURI'] = $file->getDownloadURI();
    }

    return $patches;
  }

}

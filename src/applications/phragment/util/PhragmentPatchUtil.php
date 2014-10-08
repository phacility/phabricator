<?php

final class PhragmentPatchUtil extends Phobject {

  const EMPTY_HASH = '0000000000000000000000000000000000000000';

  /**
   * Calculate the DiffMatchPatch patch between two Phabricator files.
   *
   * @phutil-external-symbol class diff_match_patch
   */
  public static function calculatePatch(
    PhabricatorFile $old = null,
    PhabricatorFile $new = null) {

    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/diff_match_patch/diff_match_patch.php';

    $old_hash = self::EMPTY_HASH;
    $new_hash = self::EMPTY_HASH;

    if ($old !== null) {
      $old_hash = $old->getContentHash();
    }
    if ($new !== null) {
      $new_hash = $new->getContentHash();
    }

    $old_content = '';
    $new_content = '';

    if ($old_hash === $new_hash) {
      return null;
    }

    if ($old_hash !== self::EMPTY_HASH) {
      $old_content = $old->loadFileData();
    } else {
      $old_content = '';
    }

    if ($new_hash !== self::EMPTY_HASH) {
      $new_content = $new->loadFileData();
    } else {
      $new_content = '';
    }

    $dmp = new diff_match_patch();
    $dmp_patches = $dmp->patch_make($old_content, $new_content);
    return $dmp->patch_toText($dmp_patches);
  }

}

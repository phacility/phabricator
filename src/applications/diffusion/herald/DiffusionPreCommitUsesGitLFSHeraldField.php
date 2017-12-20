<?php

final class DiffusionPreCommitUsesGitLFSHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.git-lfs';

  public function getHeraldFieldName() {
    return pht('Commit uses Git LFS');
  }

  public function getFieldGroupKey() {
    return DiffusionChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $map = $this->getAdapter()->getDiffContent('+');

    // At the time of writing, all current Git LFS files begin with this
    // line, verbatim:
    //
    //   version https://git-lfs.github.com/spec/v1
    //
    // ...but we don't try to match the specific version here, in the hopes
    // that this might also detect future versions.
    $pattern = '(^version\s*https://git-lfs.github.com/spec/)i';

    foreach ($map as $path => $content) {
      if (preg_match($pattern, $content)) {
        return true;
      }
    }

    return false;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_BOOL;
  }

}

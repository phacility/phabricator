<?php

final class DiffusionPreCommitContentCommitterRawHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.committer.raw';

  public function getHeraldFieldName() {
    return pht('Raw Committer');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getCommitterRaw();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}

<?php

abstract class PhabricatorFileImageTransform extends PhabricatorFileTransform {

  public function canApplyTransform(PhabricatorFile $file) {
    if (!$file->isViewableImage()) {
      return false;
    }

    if (!$file->isTransformableImage()) {
      return false;
    }

    return true;
  }

}

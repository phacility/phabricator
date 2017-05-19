<?php

final class ManiphestTaskCoverImageTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'cover-image';

  public function generateOldValue($object) {
    return $object->getCoverImageFilePHID();
  }

  public function applyInternalEffects($object, $value) {
    $file_phid = $value;

    if ($file_phid) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($file_phid))
        ->executeOne();
    } else {
      $file = null;
    }

    if (!$file || !$file->isTransformableImage()) {
      $object->setProperty('cover.filePHID', null);
      $object->setProperty('cover.thumbnailPHID', null);
      return;
    }

    $xform_key = PhabricatorFileThumbnailTransform::TRANSFORM_WORKCARD;
    $xform = PhabricatorFileTransform::getTransformByKey($xform_key)
      ->executeTransform($file);

    $object->setProperty('cover.filePHID', $file->getPHID());
    $object->setProperty('cover.thumbnailPHID', $xform->getPHID());
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      return pht(
        '%s set the cover image to %s.',
        $this->renderAuthor(),
        $this->renderHandle($new));
    }

    return pht(
      '%s updated the cover image to %s.',
      $this->renderAuthor(),
      $this->renderHandle($new));

  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if ($old === null) {
      return pht(
        '%s added a cover image to %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }

    return pht(
      '%s updated the cover image for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $viewer = $this->getActor();

    foreach ($xactions as $xaction) {
      $file_phid = $xaction->getNewValue();

      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($file_phid))
        ->executeOne();

      if (!$file) {
        $errors[] = $this->newInvalidError(
          pht('"%s" is not a valid file PHID.',
          $file_phid));
      } else {
        if (!$file->isViewableImage()) {
          $mime_type = $file->getMimeType();
          $errors[] = $this->newInvalidError(
            pht('File mime type of "%s" is not a valid viewable image.',
            $mime_type));
        }
      }

    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-image';
  }


}

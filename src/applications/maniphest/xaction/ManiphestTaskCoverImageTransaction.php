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
          pht(
            'File PHID ("%s") is invalid, or you do not have permission '.
            'to view it.',
            $file_phid),
          $xaction);
        continue;
      }

      if (!$file->isViewableImage()) {
        $errors[] = $this->newInvalidError(
          pht(
            'File ("%s", with MIME type "%s") is not a viewable image file.',
            $file_phid,
            $file->getMimeType()),
          $xaction);
        continue;
      }

      if (!$file->isTransformableImage()) {
        $errors[] = $this->newInvalidError(
          pht(
            'File ("%s", with MIME type "%s") can not be transformed into '.
            'a thumbnail. You may be missing support for this file type in '.
            'the "GD" extension.',
            $file_phid,
            $file->getMimeType()),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-image';
  }


}

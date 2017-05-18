<?php

final class PhabricatorMacroFileTransaction
  extends PhabricatorMacroTransactionType {

  const TRANSACTIONTYPE = 'macro:file';

  public function generateOldValue($object) {
    return $object->getFilePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFilePHID($value);
  }

  public function applyExternalEffects($object, $value) {
    $old = $this->generateOldValue($object);
    $new = $value;
    $all = array();
    if ($old) {
      $all[] = $old;
    }
    if ($new) {
      $all[] = $new;
    }

    $files = id(new PhabricatorFileQuery())
      ->setViewer($this->getActor())
      ->withPHIDs($all)
      ->execute();
    $files = mpull($files, null, 'getPHID');

    $old_file = idx($files, $old);
    if ($old_file) {
      $old_file->detachFromObject($object->getPHID());
    }

    $new_file = idx($files, $new);
    if ($new_file) {
      $new_file->attachToObject($object->getPHID());
    }
  }

  public function getTitle() {
    return pht(
      '%s changed the image for this macro.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the image for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $viewer = $this->getActor();

    $old_phid = $object->getFilePHID();

    foreach ($xactions as $xaction) {
      $file_phid = $xaction->getNewValue();

      if (!$old_phid) {
        if ($this->isEmptyTextTransaction($file_phid, $xactions)) {
          $errors[] = $this->newRequiredError(
            pht('Image macros must have a file.'));
          return $errors;
        }
      }

      // Only validate if file was uploaded
      if ($file_phid) {
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

    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-file-image-o';
  }

}

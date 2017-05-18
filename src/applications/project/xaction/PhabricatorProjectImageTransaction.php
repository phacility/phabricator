<?php

final class PhabricatorProjectImageTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:image';

  public function generateOldValue($object) {
    return $object->getProfileImagePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setProfileImagePHID($value);
  }

  public function applyExternalEffects($object, $value) {
    $old = $this->getOldValue();
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
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    // TODO: Some day, it would be nice to show the images.
    if (!$old) {
      return pht(
        "%s set this project's image to %s.",
        $this->renderAuthor(),
        $this->renderNewHandle());
    } else if (!$new) {
      return pht(
        "%s removed this project's image.",
        $this->renderAuthor());
    } else {
      return pht(
        "%s updated this project's image from %s to %s.",
        $this->renderAuthor(),
        $this->renderOldHandle(),
        $this->renderNewHandle());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    // TODO: Some day, it would be nice to show the images.
    if (!$old) {
      return pht(
        '%s set the image for %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewHandle());
    } else if (!$new) {
      return pht(
        '%s removed the image for %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s updated the image for %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldHandle(),
        $this->renderNewHandle());
    }
  }

  public function getIcon() {
    return 'fa-photo';
  }

  public function extractFilePHIDs($object, $value) {
    if ($value) {
      return array($value);
    }
    return array();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $viewer = $this->getActor();

    foreach ($xactions as $xaction) {
      $file_phid = $xaction->getNewValue();

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

}

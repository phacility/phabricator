<?php

final class PholioImageReplaceTransaction
  extends PholioImageTransactionType {

  const TRANSACTIONTYPE = 'image-replace';

  public function generateOldValue($object) {
    $editor = $this->getEditor();
    $new_phid = $this->getNewValue();

    return $editor->loadPholioImage($object, $new_phid)
      ->getReplacesImagePHID();
  }

  public function applyExternalEffects($object, $value) {
    $editor = $this->getEditor();
    $old_phid = $this->getOldValue();

    $old_image = $editor->loadPholioImage($object, $old_phid)
      ->setIsObsolete(1)
      ->save();

    $editor->loadPholioImage($object, $value)
      ->setMockPHID($object->getPHID())
      ->setSequence($old_image->getSequence())
      ->save();
  }

  public function getTitle() {
    return pht(
      '%s replaced %s with %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated images of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-picture-o';
  }

  public function getColor() {
    return PhabricatorTransactions::COLOR_YELLOW;
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $u_phid = $u->getOldValue();
    $v_phid = $v->getOldValue();

    if ($u_phid === $v_phid) {
      return $v;
    }

    return null;
  }

  public function extractFilePHIDs($object, $value) {
    $editor = $this->getEditor();

    $file_phid = $editor->loadPholioImage($object, $value)
      ->getFilePHID();

    return array($file_phid);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $mock_phid = $object->getPHID();

    $editor = $this->getEditor();
    foreach ($xactions as $xaction) {
      $new_phid = $xaction->getNewValue();

      try {
        $new_image = $editor->loadPholioImage($object, $new_phid);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          pht(
            'Unable to load replacement image ("%s"): %s',
            $new_phid,
            $ex->getMessage()),
          $xaction);
        continue;
      }

      $old_phid = $new_image->getReplacesImagePHID();
      if (!$old_phid) {
        $errors[] = $this->newInvalidError(
          pht(
            'Image ("%s") does not specify which image it replaces.',
            $new_phid),
          $xaction);
        continue;
      }

      try {
        $old_image = $editor->loadPholioImage($object, $old_phid);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          pht(
            'Unable to load replaced image ("%s"): %s',
            $old_phid,
            $ex->getMessage()),
          $xaction);
        continue;
      }

      if ($old_image->getMockPHID() !== $mock_phid) {
        $errors[] = $this->newInvalidError(
          pht(
            'Replaced image ("%s") belongs to the wrong mock ("%s", expected '.
            '"%s").',
            $old_phid,
            $old_image->getMockPHID(),
            $mock_phid),
          $xaction);
        continue;
      }

      // TODO: You shouldn't be able to replace an image if it has already
      // been replaced.

    }

    return $errors;
  }

}

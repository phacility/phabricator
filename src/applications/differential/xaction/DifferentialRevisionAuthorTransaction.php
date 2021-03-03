<?php

final class DifferentialRevisionAuthorTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.author';
  const EDITKEY = 'author';

  public function generateOldValue($object) {
    return $object->getAuthorPHID();
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setAuthorPHID($value);
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    foreach ($xactions as $xaction) {
      $old = $xaction->generateOldValue($object);
      $new = $xaction->getNewValue();

      if ($old === $new) {
        continue;
      }

      if (!$new) {
        $errors[] = $this->newInvalidError(
          pht('Revisions must have an assigned author.'),
          $xaction);
        continue;
      }

      $author_objects = id(new PhabricatorPeopleQuery())
        ->setViewer($actor)
        ->withPHIDs(array($new))
        ->execute();
      if (!$author_objects) {
        $errors[] = $this->newInvalidError(
          pht('Author "%s" is not a valid user.', $new),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

  public function getIcon() {
    $author_phid = $this->getAuthorPHID();
    $old_phid = $this->getOldValue();
    $new_phid = $this->getNewValue();

    $is_commandeer = ($author_phid === $new_phid);
    $is_foist = ($author_phid === $old_phid);

    if ($is_commandeer) {
      return 'fa-flag';
    }

    if ($is_foist) {
      return 'fa-gift';
    }

    return 'fa-user';
  }

  public function getColor() {
    return 'sky';
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $old_phid = $this->getOldValue();
    $new_phid = $this->getNewValue();

    $is_commandeer = ($author_phid === $new_phid);
    $is_foist = ($author_phid === $old_phid);

    if ($is_commandeer) {
      return pht(
        '%s commandeered this revision from %s.',
        $this->renderAuthor(),
        $this->renderOldHandle());
    }

    if ($is_foist) {
      if ($new_phid) {
        return pht(
          '%s foisted this revision upon %s.',
          $this->renderAuthor(),
          $this->renderNewHandle());
      } else {

        // This isn't a valid transaction that can be applied, but happens in
        // the preview if you temporarily delete the tokenizer value.

        return pht(
          '%s foisted this revision upon...',
          $this->renderAuthor());
      }
    }

    return pht(
      '%s changed the author of this revision from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $old_phid = $this->getOldValue();
    $new_phid = $this->getNewValue();

    $is_commandeer = ($author_phid === $new_phid);
    $is_foist = ($author_phid === $old_phid);

    if ($is_commandeer) {
      return pht(
        '%s commandeered %s from %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldHandle());
    }

    if ($is_foist) {
      return pht(
        '%s foisted %s upon %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewHandle());
    }

    return pht(
      '%s changed the author of %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldHandle(),
      $this->renderNewHandle());

  }

  public function getTransactionTypeForConduit($xaction) {
    return 'author';
  }

  public function getFieldValuesForConduit($object, $data) {
    return array(
      'old' => $object->getOldValue(),
      'new' => $object->getNewValue(),
    );
  }

}

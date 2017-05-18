<?php

final class PhamePostBlogTransaction
  extends PhamePostTransactionType {

  const TRANSACTIONTYPE = 'phame.post.blog';

  public function generateOldValue($object) {
    return $object->getBlogPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setBlogPHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the blog for this post.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the blog for post %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getBlogPHID(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Posts must be attached to a blog.'));
    }

    foreach ($xactions as $xaction) {
      $new_phid = $xaction->getNewValue();

      $blog = id(new PhameBlogQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($new_phid))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->execute();

      if ($blog) {
        continue;
      }

      $errors[] = $this->newInvalidError(
        pht('The specified blog PHID ("%s") is not valid. You can only '.
            'create a post on (or move a post into) a blog which you '.
            'have permission to see and edit.',
            $new_phid));
    }

    return $errors;
  }

}

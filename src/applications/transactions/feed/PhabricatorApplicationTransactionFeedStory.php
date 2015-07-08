<?php

/**
 * @concrete-extensible
 */
class PhabricatorApplicationTransactionFeedStory
  extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('objectPHID');
  }

  public function getRequiredObjectPHIDs() {
    return $this->getValue('transactionPHIDs');
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    $phids[] = $this->getValue('objectPHID');
    foreach ($this->getValue('transactionPHIDs') as $xaction_phid) {
      $xaction = $this->getObject($xaction_phid);
      foreach ($xaction->getRequiredHandlePHIDs() as $handle_phid) {
        $phids[] = $handle_phid;
      }
    }
    return $phids;
  }

  protected function getPrimaryTransactionPHID() {
    return head($this->getValue('transactionPHIDs'));
  }

  public function getPrimaryTransaction() {
    return $this->getObject($this->getPrimaryTransactionPHID());
  }

  public function getFieldStoryMarkupFields() {
    $xaction_phids = $this->getValue('transactionPHIDs');

    $fields = array();
    foreach ($xaction_phids as $xaction_phid) {
      $xaction = $this->getObject($xaction_phid);
      foreach ($xaction->getMarkupFieldsForFeed($this) as $field) {
        $fields[] = $field;
      }
    }

    return $fields;
  }

  public function getMarkupText($field) {
    $xaction_phids = $this->getValue('transactionPHIDs');

    foreach ($xaction_phids as $xaction_phid) {
      $xaction = $this->getObject($xaction_phid);
      foreach ($xaction->getMarkupFieldsForFeed($this) as $xaction_field) {
        if ($xaction_field == $field) {
          return $xaction->getMarkupTextForFeed($this, $field);
        }
      }
    }

    return null;
  }

  public function renderView() {
    $view = $this->newStoryView();

    $handle = $this->getHandle($this->getPrimaryObjectPHID());
    $view->setHref($handle->getURI());

    $type = phid_get_type($handle->getPHID());
    $phid_types = PhabricatorPHIDType::getAllTypes();
    $icon = null;
    if (!empty($phid_types[$type])) {
      $phid_type = $phid_types[$type];
      $class = $phid_type->getPHIDTypeApplicationClass();
      if ($class) {
        $application = PhabricatorApplication::getByClass($class);
        $icon = $application->getFontIcon();
      }
    }

    $view->setAppIcon($icon);

    $xaction_phids = $this->getValue('transactionPHIDs');
    $xaction = $this->getPrimaryTransaction();

    $xaction->setHandles($this->getHandles());
    $view->setTitle($xaction->getTitleForFeed());

    foreach ($xaction_phids as $xaction_phid) {
      $secondary_xaction = $this->getObject($xaction_phid);
      $secondary_xaction->setHandles($this->getHandles());

      $body = $secondary_xaction->getBodyForFeed($this);
      if (nonempty($body)) {
        $view->appendChild($body);
      }
    }

    $view->setImage(
      $this->getHandle($xaction->getAuthorPHID())->getImageURI());

    return $view;
  }

  public function renderText() {
    $xaction = $this->getPrimaryTransaction();
    $old_target = $xaction->getRenderingTarget();
    $new_target = PhabricatorApplicationTransaction::TARGET_TEXT;
    $xaction->setRenderingTarget($new_target);
    $xaction->setHandles($this->getHandles());
    $text = $xaction->getTitleForFeed();
    $xaction->setRenderingTarget($old_target);
    return $text;
  }

  public function renderTextBody() {
    $all_bodies = '';
    $new_target = PhabricatorApplicationTransaction::TARGET_TEXT;
    $xaction_phids = $this->getValue('transactionPHIDs');
    foreach ($xaction_phids as $xaction_phid) {
      $secondary_xaction = $this->getObject($xaction_phid);
      $old_target = $secondary_xaction->getRenderingTarget();
      $secondary_xaction->setRenderingTarget($new_target);
      $secondary_xaction->setHandles($this->getHandles());

      $body = $secondary_xaction->getBodyForMail();
      if (nonempty($body)) {
        $all_bodies .= $body."\n";
      }
      $secondary_xaction->setRenderingTarget($old_target);
    }
    return trim($all_bodies);
  }

  public function getImageURI() {
    $author_phid = $this->getPrimaryTransaction()->getAuthorPHID();
    return $this->getHandle($author_phid)->getImageURI();
  }

  public function getURI() {
    $handle = $this->getHandle($this->getPrimaryObjectPHID());
    return PhabricatorEnv::getProductionURI($handle->getURI());
  }

  public function renderAsTextForDoorkeeper(
    DoorkeeperFeedStoryPublisher $publisher) {

    $xactions = array();
    $xaction_phids = $this->getValue('transactionPHIDs');
    foreach ($xaction_phids as $xaction_phid) {
      $xaction = $this->getObject($xaction_phid);
      $xaction->setHandles($this->getHandles());
      $xactions[] = $xaction;
    }

    $primary = $this->getPrimaryTransaction();
    return $primary->renderAsTextForDoorkeeper($publisher, $this, $xactions);
  }

}

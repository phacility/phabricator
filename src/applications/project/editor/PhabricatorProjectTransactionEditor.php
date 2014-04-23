<?php

final class PhabricatorProjectTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_JOIN_POLICY;

    $types[] = PhabricatorProjectTransaction::TYPE_NAME;
    $types[] = PhabricatorProjectTransaction::TYPE_STATUS;
    $types[] = PhabricatorProjectTransaction::TYPE_IMAGE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorProjectTransaction::TYPE_STATUS:
        return $object->getStatus();
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        return $object->getProfileImagePHID();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        $object->setPhrictionSlug($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        $object->setProfileImagePHID($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_EDGE:
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        $object->setJoinPolicy($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        if ($xaction->getOldValue() === null) {
          // Project was just created, we don't need to move anything.
          return;
        }

        $clone_object = clone $object;
        $clone_object->setPhrictionSlug($xaction->getOldValue());
        $old_slug = $clone_object->getFullPhrictionSlug();

        $old_document = id(new PhrictionDocument())
          ->loadOneWhere('slug = %s', $old_slug);
        if ($old_document && $old_document->getStatus() ==
            PhrictionDocumentStatus::STATUS_EXISTS) {
          $content = id(new PhrictionContent())
            ->load($old_document->getContentID());
          $from_editor = id(PhrictionDocumentEditor::newForSlug($old_slug))
            ->setActor($this->getActor())
            ->setTitle($content->getTitle())
            ->setContent($content->getContent())
            ->setDescription($content->getDescription());

          $target_editor = id(PhrictionDocumentEditor::newForSlug(
            $object->getFullPhrictionSlug()))
            ->setActor($this->getActor())
            ->setTitle($content->getTitle())
            ->setContent($content->getContent())
            ->setDescription($content->getDescription())
            ->moveHere($old_document->getID(), $old_document->getPHID());

          $target_document = $target_editor->getDocument();
          $from_editor->moveAway($target_document->getID());
        }
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        return;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($xaction->getMetadataValue('edge:type')) {
          case PhabricatorEdgeConfig::TYPE_PROJ_MEMBER:
            // When project members are added or removed, add or remove their
            // subscriptions.
            $old = $xaction->getOldValue();
            $new = $xaction->getNewValue();
            $add = array_keys(array_diff_key($new, $old));
            $rem = array_keys(array_diff_key($old, $new));

            // NOTE: The subscribe is "explicit" because there's no implicit
            // unsubscribe, so Join -> Leave -> Join doesn't resubscribe you
            // if we use an implicit subscribe, even though you never willfully
            // unsubscribed. Not sure if adding implicit unsubscribe (which
            // would not write the unsubscribe row) is justified to deal with
            // this, which is a fairly weird edge case and pretty arguable both
            // ways.

            id(new PhabricatorSubscriptionsEditor())
              ->setActor($this->requireActor())
              ->setObject($object)
              ->subscribeExplicit($add)
              ->unsubscribe($rem)
              ->save();
            break;
        }
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorProjectTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Project name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }

        if (!$xactions) {
          break;
        }

        $name = last($xactions)->getNewValue();
        $name_used_already = id(new PhabricatorProjectQuery())
          ->setViewer($this->getActor())
          ->withNames(array($name))
          ->executeOne();
        if ($name_used_already &&
           ($name_used_already->getPHID() != $object->getPHID())) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht(''),
            pht('Project name is already used.'),
            nonempty(last($xactions), null));
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }


  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_NAME:
      case PhabricatorProjectTransaction::TYPE_STATUS:
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        PhabricatorPolicyFilter::requireCapability(
          $this->requireActor(),
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        return;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($xaction->getMetadataValue('edge:type')) {
          case PhabricatorEdgeConfig::TYPE_PROJ_MEMBER:
            $old = $xaction->getOldValue();
            $new = $xaction->getNewValue();

            $add = array_keys(array_diff_key($new, $old));
            $rem = array_keys(array_diff_key($old, $new));

            $actor_phid = $this->requireActor()->getPHID();

            $is_join = (($add === array($actor_phid)) && !$rem);
            $is_leave = (($rem === array($actor_phid)) && !$add);

            if ($is_join) {
              // You need CAN_JOIN to join a project.
              PhabricatorPolicyFilter::requireCapability(
                $this->requireActor(),
                $object,
                PhabricatorPolicyCapability::CAN_JOIN);
            } else if ($is_leave) {
              // You don't need any capabilities to leave a project.
            } else {
              // You need CAN_EDIT to change members other than yourself.
              PhabricatorPolicyFilter::requireCapability(
                $this->requireActor(),
                $object,
                PhabricatorPolicyCapability::CAN_EDIT);
            }
            return;
        }
        break;
    }

    return parent::requireCapabilities($object, $xaction);
  }

  protected function supportsSearch() {
    return true;
  }

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectTransaction::TYPE_IMAGE:
        $new = $xaction->getNewValue();
        if ($new) {
          return array($new);
        }
        break;
    }

    return parent::extractFilePHIDsFromCustomTransaction($object, $xaction);
  }

}

<?php

final class PhabricatorBadgesEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Badges');
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorBadgesTransaction::TYPE_NAME;
    $types[] = PhabricatorBadgesTransaction::TYPE_FLAVOR;
    $types[] = PhabricatorBadgesTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorBadgesTransaction::TYPE_ICON;
    $types[] = PhabricatorBadgesTransaction::TYPE_STATUS;
    $types[] = PhabricatorBadgesTransaction::TYPE_QUALITY;
    $types[] = PhabricatorBadgesTransaction::TYPE_AWARD;
    $types[] = PhabricatorBadgesTransaction::TYPE_REVOKE;

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorBadgesTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorBadgesTransaction::TYPE_FLAVOR:
        return $object->getFlavor();
      case PhabricatorBadgesTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorBadgesTransaction::TYPE_ICON:
        return $object->getIcon();
      case PhabricatorBadgesTransaction::TYPE_QUALITY:
        return (int)$object->getQuality();
      case PhabricatorBadgesTransaction::TYPE_STATUS:
        return $object->getStatus();
      case PhabricatorBadgesTransaction::TYPE_AWARD:
        $award_phids = mpull($object->getAwards(), 'getRecipientPHID');
        return $award_phids;
      case PhabricatorBadgesTransaction::TYPE_REVOKE:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorBadgesTransaction::TYPE_NAME:
      case PhabricatorBadgesTransaction::TYPE_FLAVOR:
      case PhabricatorBadgesTransaction::TYPE_DESCRIPTION:
      case PhabricatorBadgesTransaction::TYPE_ICON:
      case PhabricatorBadgesTransaction::TYPE_STATUS:
      case PhabricatorBadgesTransaction::TYPE_AWARD:
      case PhabricatorBadgesTransaction::TYPE_REVOKE:
        return $xaction->getNewValue();
      case PhabricatorBadgesTransaction::TYPE_QUALITY:
        return (int)$xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorBadgesTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorBadgesTransaction::TYPE_FLAVOR:
        $object->setFlavor($xaction->getNewValue());
        return;
      case PhabricatorBadgesTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhabricatorBadgesTransaction::TYPE_ICON:
        $object->setIcon($xaction->getNewValue());
        return;
      case PhabricatorBadgesTransaction::TYPE_QUALITY:
        $object->setQuality($xaction->getNewValue());
        return;
      case PhabricatorBadgesTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case PhabricatorBadgesTransaction::TYPE_AWARD:
      case PhabricatorBadgesTransaction::TYPE_REVOKE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorBadgesTransaction::TYPE_NAME:
      case PhabricatorBadgesTransaction::TYPE_FLAVOR:
      case PhabricatorBadgesTransaction::TYPE_DESCRIPTION:
      case PhabricatorBadgesTransaction::TYPE_ICON:
      case PhabricatorBadgesTransaction::TYPE_STATUS:
      case PhabricatorBadgesTransaction::TYPE_QUALITY:
        return;
      case PhabricatorBadgesTransaction::TYPE_REVOKE:
        $revoked_recipient_phids = $xaction->getNewValue();
        $awards = $object->getAwards();
        $awards = mpull($awards, null, 'getRecipientPHID');

        foreach ($revoked_recipient_phids as $phid) {
          $awards[$phid]->delete();
        }
        $object->attachAwards($awards);
        return;
      case PhabricatorBadgesTransaction::TYPE_AWARD:
        $recipient_phids = $xaction->getNewValue();
        $awards = $object->getAwards();
        $awards = mpull($awards, null, 'getRecipientPHID');

        foreach ($recipient_phids as $phid) {
          $award = idx($awards, $phid);
          if (!$award) {
            $award = PhabricatorBadgesAward::initializeNewBadgesAward(
              $this->getActor(),
              $object,
              $phid);
            $award->save();
            $awards[] = $award;
          }
        }
        $object->attachAwards($awards);
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
      case PhabricatorBadgesTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Badge name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
      break;
    }

    return $errors;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorBadgesTransaction::MAILTAG_DETAILS =>
        pht('Someone changes the badge\'s details.'),
      PhabricatorBadgesTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a badge.'),
      PhabricatorBadgesTransaction::MAILTAG_OTHER =>
        pht('Other badge activity not listed above occurs.'),
    );
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorBadgesReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();
    $id = $object->getID();
    $name = pht('Badge %d', $id);
    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name)
      ->addHeader('Thread-Topic', $name);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getCreatorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $description = $object->getDescription();
    $body = parent::buildMailBody($object, $xactions);

    if (strlen($description)) {
      $body->addRemarkupSection(
        pht('BADGE DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('BADGE DETAIL'),
      PhabricatorEnv::getProductionURI('/badges/view/'.$object->getID().'/'));
    return $body;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Badge]');
  }

}

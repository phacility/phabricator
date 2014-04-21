<?php

final class PhabricatorTokenGivenEditor
  extends PhabricatorEditor {

  private $contentSource;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function addToken($object_phid, $token_phid) {
    $token = $this->validateToken($token_phid);
    $object = $this->validateObject($object_phid);
    $current_token = $this->loadCurrentToken($object);

    $actor = $this->requireActor();

    $token_given = id(new PhabricatorTokenGiven())
      ->setAuthorPHID($actor->getPHID())
      ->setObjectPHID($object->getPHID())
      ->setTokenPHID($token->getPHID());

    $token_given->openTransaction();

      if ($current_token) {
        $this->executeDeleteToken($object, $current_token);
      }

      $token_given->save();

      queryfx(
        $token_given->establishConnection('w'),
        'INSERT INTO %T (objectPHID, tokenCount) VALUES (%s, 1)
          ON DUPLICATE KEY UPDATE tokenCount = tokenCount + 1',
        id(new PhabricatorTokenCount())->getTableName(),
        $object->getPHID());

    $token_given->saveTransaction();

    $current_token_phid = null;
    if ($current_token) {
      $current_token_phid = $current_token->getTokenPHID();
    }

    $this->publishTransaction(
      $object,
      $current_token_phid,
      $token->getPHID());

    $subscribed_phids = $object->getUsersToNotifyOfTokenGiven();
    if ($subscribed_phids) {
      $related_phids = $subscribed_phids;
      $related_phids[] = $actor->getPHID();

      $story_type = 'PhabricatorTokenGivenFeedStory';
      $story_data = array(
        'authorPHID' => $actor->getPHID(),
        'tokenPHID' => $token->getPHID(),
        'objectPHID' => $object->getPHID(),
      );

      id(new PhabricatorFeedStoryPublisher())
        ->setStoryType($story_type)
        ->setStoryData($story_data)
        ->setStoryTime(time())
        ->setStoryAuthorPHID($actor->getPHID())
        ->setRelatedPHIDs($related_phids)
        ->setPrimaryObjectPHID($object->getPHID())
        ->setSubscribedPHIDs($subscribed_phids)
        ->publish();
    }

    return $token_given;
  }

  public function deleteToken($object_phid) {
    $object = $this->validateObject($object_phid);
    $token_given = $this->loadCurrentToken($object);
    if (!$token_given) {
      return;
    }

    $this->executeDeleteToken($object, $token_given);
    $this->publishTransaction(
      $object,
      $token_given->getTokenPHID(),
      null);
  }

  private function executeDeleteToken(
    PhabricatorTokenReceiverInterface $object,
    PhabricatorTokenGiven $token_given) {

    $token_given->openTransaction();
      $token_given->delete();

      queryfx(
        $token_given->establishConnection('w'),
        'INSERT INTO %T (objectPHID, tokenCount) VALUES (%s, 0)
          ON DUPLICATE KEY UPDATE tokenCount = tokenCount - 1',
        id(new PhabricatorTokenCount())->getTableName(),
        $object->getPHID());
    $token_given->saveTransaction();
  }

  private function validateToken($token_phid) {
    $token = id(new PhabricatorTokenQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs(array($token_phid))
      ->executeOne();

    if (!$token) {
      throw new Exception(pht('No such token "%s"!', $token_phid));
    }

    return $token;
  }

  private function validateObject($object_phid) {
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs(array($object_phid))
      ->executeOne();

    if (!$object) {
      throw new Exception(pht('No such object "%s"!', $object_phid));
    }

    return $object;
  }

  private function loadCurrentToken(PhabricatorTokenReceiverInterface $object) {
    return id(new PhabricatorTokenGiven())->loadOneWhere(
      'authorPHID = %s AND objectPHID = %s',
      $this->requireActor()->getPHID(),
      $object->getPHID());
  }


  private function publishTransaction(
    PhabricatorTokenReceiverInterface $object,
    $old_token_phid,
    $new_token_phid) {

    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      return;
    }

    $actor = $this->requireActor();

    $xactions = array();
    $xactions[] = id($object->getApplicationTransactionTemplate())
      ->setTransactionType(PhabricatorTransactions::TYPE_TOKEN)
      ->setOldValue($old_token_phid)
      ->setNewValue($new_token_phid);

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($actor)
      ->setContentSource($this->getContentSource())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $editor->applyTransactions(
      $object->getApplicationTransactionObject(),
      $xactions);
  }

}

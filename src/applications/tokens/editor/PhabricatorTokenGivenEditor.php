<?php

final class PhabricatorTokenGivenEditor
  extends PhabricatorEditor {

  public function addToken($object_phid, $token_phid) {
    $token = $this->validateToken($token_phid);
    $object = $this->validateObject($object_phid);

    $actor = $this->requireActor();

    $token_given = id(new PhabricatorTokenGiven())
      ->setAuthorPHID($actor->getPHID())
      ->setObjectPHID($object->getPHID())
      ->setTokenPHID($token->getPHID());

    $token_given->openTransaction();

      $this->executeDeleteToken($object);

      $token_given->save();

      queryfx(
        $token_given->establishConnection('w'),
        'INSERT INTO %T (objectPHID, tokenCount) VALUES (%s, 1)
          ON DUPLICATE KEY UPDATE tokenCount = tokenCount + 1',
        id(new PhabricatorTokenCount())->getTableName(),
        $object->getPHID());

    $token_given->saveTransaction();

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
    return $this->executeDeleteToken($object);
  }

  private function executeDeleteToken($object) {
    $actor = $this->requireActor();

    $token_given = id(new PhabricatorTokenGiven())->loadOneWhere(
      'authorPHID = %s AND objectPHID = %s',
      $actor->getPHID(),
      $object->getPHID());
    if (!$token_given) {
      return;
    }

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
    $tokens = id(new PhabricatorTokenQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs(array($token_phid))
      ->execute();

    if (empty($tokens)) {
      throw new Exception("No such token!");
    }

    return head($tokens);
  }

  private function validateObject($object_phid) {
    $objects = id(new PhabricatorObjectHandleData(array($object_phid)))
      ->setViewer($this->requireActor())
      ->loadObjects();
    $object = head($objects);

    if (!$object) {
      throw new Exception("No such object!");
    }

    return $object;
  }

}

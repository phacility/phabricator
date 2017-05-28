<?php

final class DifferentialDoorkeeperRevisionFeedStoryPublisher
  extends DoorkeeperFeedStoryPublisher {

  public function canPublishStory(PhabricatorFeedStory $story, $object) {
    return ($object instanceof DifferentialRevision);
  }

  public function isStoryAboutObjectCreation($object) {
    $story = $this->getFeedStory();
    $action = $story->getStoryData()->getValue('action');

    return ($action == DifferentialAction::ACTION_CREATE);
  }

  public function isStoryAboutObjectClosure($object) {
    $story = $this->getFeedStory();
    $action = $story->getStoryData()->getValue('action');

    return ($action == DifferentialAction::ACTION_CLOSE) ||
           ($action == DifferentialAction::ACTION_ABANDON);
  }

  public function willPublishStory($object) {
    return id(new DifferentialRevisionQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($object->getID()))
      ->needReviewers(true)
      ->executeOne();
  }

  public function getOwnerPHID($object) {
    return $object->getAuthorPHID();
  }

  public function getActiveUserPHIDs($object) {
    $status = $object->getStatus();
    if ($status == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      return $object->getReviewerPHIDs();
    } else {
      return array();
    }
  }

  public function getPassiveUserPHIDs($object) {
    $status = $object->getStatus();
    if ($status == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      return array();
    } else {
      return $object->getReviewerPHIDs();
    }
  }

  public function getCCUserPHIDs($object) {
    return PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $object->getPHID());
  }

  public function getObjectTitle($object) {
    $id = $object->getID();

    $title = $object->getTitle();

    return "D{$id}: {$title}";
  }

  public function getObjectURI($object) {
    return PhabricatorEnv::getProductionURI('/D'.$object->getID());
  }

  public function getObjectDescription($object) {
    return $object->getSummary();
  }

  public function isObjectClosed($object) {
    return $object->isClosed();
  }

  public function getResponsibilityTitle($object) {
    $prefix = $this->getTitlePrefix($object);
    return pht('%s Review Request', $prefix);
  }

  private function getTitlePrefix(DifferentialRevision $revision) {
    $prefix_key = 'metamta.differential.subject-prefix';
    return PhabricatorEnv::getEnvConfig($prefix_key);
  }

}

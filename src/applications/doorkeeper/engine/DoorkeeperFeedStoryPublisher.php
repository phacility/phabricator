<?php

abstract class DoorkeeperFeedStoryPublisher {

  private $feedStory;
  private $viewer;

  public function setFeedStory(PhabricatorFeedStory $feed_story) {
    $this->feedStory = $feed_story;
    return $this;
  }

  public function getFeedStory() {
    return $this->feedStory;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  abstract public function canPublishStory(
    PhabricatorFeedStory $story,
    $object);

  /**
   * Hook for publishers to mutate the story object, particularly by loading
   * and attaching additional data.
   */
  public function willPublishStory($object) {
    return $object;
  }

  abstract public function isStoryAboutObjectCreation($object);
  abstract public function isStoryAboutObjectClosure($object);
  abstract public function getOwnerPHID($object);
  abstract public function getActiveUserPHIDs($object);
  abstract public function getPassiveUserPHIDs($object);
  abstract public function getCCUserPHIDs($object);
  abstract public function getObjectTitle($object);
  abstract public function getObjectURI($object);
  abstract public function getObjectDescription($object);
  abstract public function isObjectClosed($object);
  abstract public function getResponsibilityTitle($object);
  abstract public function getStoryText($object);

}

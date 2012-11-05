<?php

abstract class PhabricatorFeedStoryAggregate extends PhabricatorFeedStory {

  private $aggregateStories = array();

  public function getHasViewed() {
    return head($this->getAggregateStories())->getHasViewed();
  }

  public function getPrimaryObjectPHID() {
    return head($this->getAggregateStories())->getPrimaryObjectPHID();
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->getAggregateStories() as $story) {
      $phids[] = $story->getRequiredHandlePHIDs();
    }
    return array_mergev($phids);
  }

  public function getRequiredObjectPHIDs() {
    $phids = array();
    foreach ($this->getAggregateStories() as $story) {
      $phids[] = $story->getRequiredObjectPHIDs();
    }
    return array_mergev($phids);
  }

  protected function getAuthorPHIDs() {
    $authors = array();
    foreach ($this->getAggregateStories() as $story) {
      $authors[] = $story->getStoryData()->getAuthorPHID();
    }
    return array_unique(array_filter($authors));
  }

  protected function getDataValues($key, $default) {
    $result = array();
    foreach ($this->getAggregateStories() as $key => $story) {
      $result[$key] = $story->getStoryData()->getValue($key, $default);
    }
    return $result;
  }

  final public function setAggregateStories(array $aggregate_stories) {
    assert_instances_of($aggregate_stories, 'PhabricatorFeedStory');
    $this->aggregateStories = $aggregate_stories;

    $objects = array();
    $handles = array();

    foreach ($this->aggregateStories as $story) {
      $objects += $story->getObjects();
      $handles += $story->getHandles();
    }

    $this->setObjects($objects);
    $this->setHandles($handles);

    return $this;
  }

  final public function getAggregateStories() {
    return $this->aggregateStories;
  }

  final public function getNotificationAggregations() {
    throw new Exception(
      "You can not get aggregations for an aggregate story.");
  }

}

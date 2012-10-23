<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

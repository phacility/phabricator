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

/**
 * Manages rendering and aggregation of a story. A story is an event (like a
 * user adding a comment) which may be represented in different forms on
 * different channels (like feed, notifications and realtime alerts).
 *
 * @task load Loading Stories
 */
abstract class PhabricatorFeedStory implements PhabricatorPolicyInterface {

  private $data;
  private $hasViewed;
  private $handles;
  private $framed;
  private $primaryObjectPHID;


/* -(  Loading Stories  )---------------------------------------------------- */


  /**
   * Given @{class:PhabricatorFeedStoryData} rows, load them into objects and
   * construct appropriate @{class:PhabricatorFeedStory} wrappers for each
   * data row.
   *
   * @param list<dict>  List of @{class:PhabricatorFeedStoryData} rows from the
   *                    database.
   * @return list<PhabricatorFeedStory>   List of @{class:PhabricatorFeedStory}
   *                                      objects.
   * @task load
   */
  public static function loadAllFromRows(array $rows) {
    $stories = array();

    $data = id(new PhabricatorFeedStoryData())->loadAllFromArray($rows);
    foreach ($data as $story_data) {
      $class = $story_data->getStoryType();

      try {
        $ok =
          class_exists($class) &&
          is_subclass_of($class, 'PhabricatorFeedStory');
      } catch (PhutilMissingSymbolException $ex) {
        $ok = false;
      }

      // If the story type isn't a valid class or isn't a subclass of
      // PhabricatorFeedStory, load it as PhabricatorFeedStoryUnknown.

      if (!$ok) {
        $class = 'PhabricatorFeedStoryUnknown';
      }

      $key = $story_data->getChronologicalKey();
      $stories[$key] = newv($class, array($story_data));
    }

    return $stories;
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorEnv::getEnvConfig('feed.public')
      ? PhabricatorPolicies::POLICY_PUBLIC
      : PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function setPrimaryObjectPHID($primary_object_phid) {
    $this->primaryObjectPHID = $primary_object_phid;
    return $this;
  }

  public function getPrimaryObjectPHID() {
    return $this->primaryObjectPHID;
  }

  final public function __construct(PhabricatorFeedStoryData $data) {
    $this->data = $data;
  }

  abstract public function renderView();

//  TODO: Make abstract once all subclasses implement it.
  public function renderNotificationView() {
    return id(new PhabricatorFeedStoryUnknown($this->data))
      ->renderNotificationView();
  }

  public function getRequiredHandlePHIDs() {
    return array();
  }

  public function setHasViewed($has_viewed) {
    $this->hasViewed = $has_viewed;
    return $this;
  }

  public function getHasViewed() {
    return $this->hasViewed;
  }

  public function getRequiredObjectPHIDs() {
    return array();
  }

  final public function setFramed($framed) {
    $this->framed = $framed;
    return $this;
  }

  final public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  final protected function getHandles() {
    return $this->handles;
  }

  final protected function getHandle($phid) {
    if (isset($this->handles[$phid])) {
      if ($this->handles[$phid] instanceof PhabricatorObjectHandle) {
        return $this->handles[$phid];
      }
    }

    $handle = new PhabricatorObjectHandle();
    $handle->setPHID($phid);
    $handle->setName("Unloaded Object '{$phid}'");

    return $handle;
  }

  final public function getStoryData() {
    return $this->data;
  }

  final public function getEpoch() {
    return $this->getStoryData()->getEpoch();
  }

  final public function getChronologicalKey() {
    return $this->getStoryData()->getChronologicalKey();
  }

  final protected function renderHandleList(array $phids) {
    $list = array();
    foreach ($phids as $phid) {
      $list[] = $this->linkTo($phid);
    }
    return implode(', ', $list);
  }

  final protected function linkTo($phid) {
    $handle = $this->getHandle($phid);

    // NOTE: We render our own link here to customize the styling and add
    // the '_top' target for framed feeds.

    return phutil_render_tag(
      'a',
      array(
        'href'    => $handle->getURI(),
        'target'  => $this->framed ? '_top' : null,
      ),
      phutil_escape_html($handle->getLinkName()));
  }

  final protected function renderString($str) {
    return '<strong>'.phutil_escape_html($str).'</strong>';
  }

  final protected function renderSummary($text, $len = 128) {
    if ($len) {
      $text = phutil_utf8_shorten($text, $len);
    }
    $text = phutil_escape_html($text);
    $text = str_replace("\n", '<br />', $text);
    return $text;
  }

  public function getNotificationAggregations() {
    return array();
  }

}

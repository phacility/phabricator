<?php

/**
 * Manages rendering and aggregation of a story. A story is an event (like a
 * user adding a comment) which may be represented in different forms on
 * different channels (like feed, notifications and realtime alerts).
 *
 * @task load     Loading Stories
 * @task policy   Policy Implementation
 */
abstract class PhabricatorFeedStory implements PhabricatorPolicyInterface {

  private $data;
  private $hasViewed;
  private $framed;

  private $handles  = array();
  private $objects  = array();


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
  public static function loadAllFromRows(array $rows, PhabricatorUser $viewer) {
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
      // PhabricatorFeedStory, decline to load it.
      if (!$ok) {
        continue;
      }

      $key = $story_data->getChronologicalKey();
      $stories[$key] = newv($class, array($story_data));
    }

    $object_phids = array();
    $key_phids = array();
    foreach ($stories as $key => $story) {
      $phids = array();
      foreach ($story->getRequiredObjectPHIDs() as $phid) {
        $phids[$phid] = true;
      }
      if ($story->getPrimaryObjectPHID()) {
        $phids[$story->getPrimaryObjectPHID()] = true;
      }
      $key_phids[$key] = $phids;
      $object_phids += $phids;
    }

    $objects = id(new PhabricatorObjectHandleData(array_keys($object_phids)))
      ->setViewer($viewer)
      ->loadObjects();

    foreach ($key_phids as $key => $phids) {
      if (!$phids) {
        continue;
      }
      $story_objects = array_select_keys($objects, array_keys($phids));
      if (count($story_objects) != count($phids)) {
        // An object this story requires either does not exist or is not visible
        // to the user. Decline to render the story.
        unset($stories[$key]);
        unset($key_phids[$key]);
        continue;
      }

      $stories[$key]->setObjects($story_objects);
    }

    $handle_phids = array();
    foreach ($stories as $key => $story) {
      foreach ($story->getRequiredHandlePHIDs() as $phid) {
        $key_phids[$key][$phid] = true;
      }
      if ($story->getAuthorPHID()) {
        $key_phids[$key][$story->getAuthorPHID()] = true;
      }
      $handle_phids += $key_phids[$key];
    }

    $handles = id(new PhabricatorObjectHandleData(array_keys($handle_phids)))
      ->setViewer($viewer)
      ->loadHandles();

    foreach ($key_phids as $key => $phids) {
      if (!$phids) {
        continue;
      }
      $story_handles = array_select_keys($handles, array_keys($phids));
      $stories[$key]->setHandles($story_handles);
    }

    return $stories;
  }

  public function setObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  public function getObject($phid) {
    $object = idx($this->objects, $phid);
    if (!$object) {
      throw new Exception(
        "Story is asking for an object it did not request ('{$phid}')!");
    }
    return $object;
  }

  public function getPrimaryObject() {
    $phid = $this->getPrimaryObjectPHID();
    if (!$phid) {
      throw new Exception("Story has no primary object!");
    }
    return $this->getObject($phid);
  }

  public function getPrimaryObjectPHID() {
    return null;
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

  public function getRequiredObjectPHIDs() {
    return array();
  }

  public function setHasViewed($has_viewed) {
    $this->hasViewed = $has_viewed;
    return $this;
  }

  public function getHasViewed() {
    return $this->hasViewed;
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

  final protected function getObjects() {
    return $this->objects;
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

  final public function getValue($key, $default = null) {
    return $this->getStoryData()->getValue($key, $default);
  }

  final public function getAuthorPHID() {
    return $this->getStoryData()->getAuthorPHID();
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


/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  /**
   * @task policy
   */
  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }


  /**
   * @task policy
   */
  public function getPolicy($capability) {
    // If this story's primary object is a policy-aware object, use its policy
    // to control story visiblity.

    $primary_phid = $this->getPrimaryObjectPHID();
    if (isset($this->objects[$primary_phid])) {
      $object = $this->objects[$primary_phid];
      if ($object instanceof PhabricatorPolicyInterface) {
        return $object->getPolicy($capability);
      }
    }

    // TODO: Remove this once all objects are policy-aware. For now, keep
    // respecting the `feed.public` setting.
    return PhabricatorEnv::getEnvConfig('feed.public')
      ? PhabricatorPolicies::POLICY_PUBLIC
      : PhabricatorPolicies::POLICY_USER;
  }


  /**
   * @task policy
   */
  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}

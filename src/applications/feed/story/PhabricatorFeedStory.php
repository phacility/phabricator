<?php

/**
 * Manages rendering and aggregation of a story. A story is an event (like a
 * user adding a comment) which may be represented in different forms on
 * different channels (like feed, notifications and realtime alerts).
 *
 * @task load     Loading Stories
 * @task policy   Policy Implementation
 */
abstract class PhabricatorFeedStory
  extends Phobject
  implements
    PhabricatorPolicyInterface,
    PhabricatorMarkupInterface {

  private $data;
  private $hasViewed;
  private $framed;
  private $hovercard = false;
  private $renderingTarget = PhabricatorApplicationTransaction::TARGET_HTML;

  private $handles = array();
  private $objects = array();
  private $projectPHIDs = array();
  private $markupFieldOutput = array();

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
          is_subclass_of($class, __CLASS__);
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

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array_keys($object_phids))
      ->execute();

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

    // If stories are about PhabricatorProjectInterface objects, load the
    // projects the objects are a part of so we can render project tags
    // on the stories.

    $project_phids = array();
    foreach ($objects as $object) {
      if ($object instanceof PhabricatorProjectInterface) {
        $project_phids[$object->getPHID()] = array();
      }
    }

    if ($project_phids) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array_keys($project_phids))
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));
      $edge_query->execute();
      foreach ($project_phids as $phid => $ignored) {
        $project_phids[$phid] = $edge_query->getDestinationPHIDs(array($phid));
      }
    }

    $handle_phids = array();
    foreach ($stories as $key => $story) {
      foreach ($story->getRequiredHandlePHIDs() as $phid) {
        $key_phids[$key][$phid] = true;
      }
      if ($story->getAuthorPHID()) {
        $key_phids[$key][$story->getAuthorPHID()] = true;
      }

      $object_phid = $story->getPrimaryObjectPHID();
      $object_project_phids = idx($project_phids, $object_phid, array());
      $story->setProjectPHIDs($object_project_phids);
      foreach ($object_project_phids as $dst) {
        $key_phids[$key][$dst] = true;
      }

      $handle_phids += $key_phids[$key];
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array_keys($handle_phids))
      ->execute();

    foreach ($key_phids as $key => $phids) {
      if (!$phids) {
        continue;
      }
      $story_handles = array_select_keys($handles, array_keys($phids));
      $stories[$key]->setHandles($story_handles);
    }

    // Load and process story markup blocks.

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    foreach ($stories as $story) {
      foreach ($story->getFieldStoryMarkupFields() as $field) {
        $engine->addObject($story, $field);
      }
    }

    $engine->process();

    foreach ($stories as $story) {
      foreach ($story->getFieldStoryMarkupFields() as $field) {
        $story->setMarkupFieldOutput(
          $field,
          $engine->getOutput($story, $field));
      }
    }

    return $stories;
  }

  public function setMarkupFieldOutput($field, $output) {
    $this->markupFieldOutput[$field] = $output;
    return $this;
  }

  public function getMarkupFieldOutput($field) {
    if (!array_key_exists($field, $this->markupFieldOutput)) {
      throw new Exception(
        pht(
          'Trying to retrieve markup field key "%s", but this feed story '.
          'did not request it be rendered.',
          $field));
    }

    return $this->markupFieldOutput[$field];
  }

  public function setHovercard($hover) {
    $this->hovercard = $hover;
    return $this;
  }

  public function setRenderingTarget($target) {
    $this->validateRenderingTarget($target);
    $this->renderingTarget = $target;
    return $this;
  }

  public function getRenderingTarget() {
    return $this->renderingTarget;
  }

  private function validateRenderingTarget($target) {
    switch ($target) {
      case PhabricatorApplicationTransaction::TARGET_HTML:
      case PhabricatorApplicationTransaction::TARGET_TEXT:
        break;
      default:
        throw new Exception(pht('Unknown rendering target: %s', $target));
        break;
    }
  }

  public function setObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  public function getObject($phid) {
    $object = idx($this->objects, $phid);
    if (!$object) {
      throw new Exception(
        pht(
          "Story is asking for an object it did not request ('%s')!",
          $phid));
    }
    return $object;
  }

  public function getPrimaryObject() {
    $phid = $this->getPrimaryObjectPHID();
    if (!$phid) {
      throw new Exception(pht('Story has no primary object!'));
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
  public function renderAsTextForDoorkeeper(
    DoorkeeperFeedStoryPublisher $publisher) {

    // TODO: This (and text rendering) should be properly abstract and
    // universal. However, this is far less bad than it used to be, and we
    // need to clean up more old feed code to really make this reasonable.

    return pht(
      '(Unable to render story of class %s for Doorkeeper.)',
      get_class($this));
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
    $handle->setName(pht("Unloaded Object '%s'", $phid));

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
    $items = array();
    foreach ($phids as $phid) {
      $items[] = $this->linkTo($phid);
    }
    $list = null;
    switch ($this->getRenderingTarget()) {
      case PhabricatorApplicationTransaction::TARGET_TEXT:
        $list = implode(', ', $items);
        break;
      case PhabricatorApplicationTransaction::TARGET_HTML:
        $list = phutil_implode_html(', ', $items);
        break;
    }
    return $list;
  }

  final protected function linkTo($phid) {
    $handle = $this->getHandle($phid);

    switch ($this->getRenderingTarget()) {
      case PhabricatorApplicationTransaction::TARGET_TEXT:
        return $handle->getLinkName();
    }

    // NOTE: We render our own link here to customize the styling and add
    // the '_top' target for framed feeds.

    $class = null;
    if ($handle->getType() == PhabricatorPeopleUserPHIDType::TYPECONST) {
      $class = 'phui-link-person';
    }

    return javelin_tag(
      'a',
      array(
        'href'    => $handle->getURI(),
        'target'  => $this->framed ? '_top' : null,
        'sigil'   => $this->hovercard ? 'hovercard' : null,
        'meta'    => $this->hovercard ? array('hoverPHID' => $phid) : null,
        'class'   => $class,
      ),
      $handle->getLinkName());
  }

  final protected function renderString($str) {
    switch ($this->getRenderingTarget()) {
      case PhabricatorApplicationTransaction::TARGET_TEXT:
        return $str;
      case PhabricatorApplicationTransaction::TARGET_HTML:
        return phutil_tag('strong', array(), $str);
    }
  }

  final public function renderSummary($text, $len = 128) {
    if ($len) {
      $text = id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs($len)
        ->truncateString($text);
    }
    switch ($this->getRenderingTarget()) {
      case PhabricatorApplicationTransaction::TARGET_HTML:
        $text = phutil_escape_html_newlines($text);
        break;
    }
    return $text;
  }

  public function getNotificationAggregations() {
    return array();
  }

  protected function newStoryView() {
    $view = id(new PHUIFeedStoryView())
      ->setChronologicalKey($this->getChronologicalKey())
      ->setEpoch($this->getEpoch())
      ->setViewed($this->getHasViewed());

    $project_phids = $this->getProjectPHIDs();
    if ($project_phids) {
      $view->setTags($this->renderHandleList($project_phids));
    }

    return $view;
  }

  public function setProjectPHIDs(array $phids) {
    $this->projectPHIDs = $phids;
    return $this;
  }

  public function getProjectPHIDs() {
    return $this->projectPHIDs;
  }

  public function getFieldStoryMarkupFields() {
    return array();
  }


/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */

  public function getPHID() {
    return null;
  }

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
    $policy_object = $this->getPrimaryPolicyObject();
    if ($policy_object) {
      return $policy_object->getPolicy($capability);
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
    $policy_object = $this->getPrimaryPolicyObject();
    if ($policy_object) {
      return $policy_object->hasAutomaticCapability($capability, $viewer);
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


  /**
   * Get the policy object this story is about, if such a policy object
   * exists.
   *
   * @return PhabricatorPolicyInterface|null Policy object, if available.
   * @task policy
   */
  private function getPrimaryPolicyObject() {
    $primary_phid = $this->getPrimaryObjectPHID();
    if (empty($this->objects[$primary_phid])) {
      $object = $this->objects[$primary_phid];
      if ($object instanceof PhabricatorPolicyInterface) {
        return $object;
      }
    }

    return null;
  }


/* -(  PhabricatorMarkupInterface Implementation )--------------------------- */


  public function getMarkupFieldKey($field) {
    return 'feed:'.$this->getChronologicalKey().':'.$field;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    throw new PhutilMethodNotImplementedException();
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return true;
  }

}

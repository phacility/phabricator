<?php

/**
 * @task config Configuration
 */
abstract class DoorkeeperFeedStoryPublisher extends Phobject {

  private $feedStory;
  private $viewer;
  private $renderWithImpliedContext;


/* -(  Configuration  )------------------------------------------------------ */


  /**
   * Render story text using contextual language to identify the object the
   * story is about, instead of the full object name. For example, without
   * contextual language a story might render like this:
   *
   *   alincoln created D123: Chop Wood for Log Cabin v2.0
   *
   * With contextual language, it will render like this instead:
   *
   *   alincoln created this revision.
   *
   * If the interface where the text will be displayed is specific to an
   * individual object (like Asana tasks that represent one review or commit
   * are), it's generally more natural to use language that assumes context.
   * If the target context may show information about several objects (like
   * JIRA issues which can have several linked revisions), it's generally
   * more useful not to assume context.
   *
   * @param bool  True to assume object context when rendering.
   * @return this
   * @task config
   */
  public function setRenderWithImpliedContext($render_with_implied_context) {
    $this->renderWithImpliedContext = $render_with_implied_context;
    return $this;
  }

  /**
   * Determine if rendering should assume object context. For discussion, see
   * @{method:setRenderWithImpliedContext}.
   *
   * @return bool True if rendering should assume object context is implied.
   * @task config
   */
  public function getRenderWithImpliedContext() {
    return $this->renderWithImpliedContext;
  }

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


  public function getStoryText($object) {
    return $this->getFeedStory()->renderAsTextForDoorkeeper($this);
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

}

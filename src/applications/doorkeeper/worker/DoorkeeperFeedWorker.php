<?php

/**
 * Publish events (like comments on a revision) to external objects which are
 * linked through Doorkeeper (like a linked JIRA or Asana task).
 *
 * These workers are invoked by feed infrastructure during normal task queue
 * operations. They read feed stories and publish information about them to
 * external systems, generally mirroring comments and updates in Phabricator
 * into remote systems by making API calls.
 *
 * @task publish  Publishing Stories
 * @task context  Story Context
 * @task internal Internals
 */
abstract class DoorkeeperFeedWorker extends FeedPushWorker {

  private $publisher;
  private $feedStory;
  private $storyObject;


/* -(  Publishing Stories  )------------------------------------------------- */


  /**
   * Actually publish the feed story. Subclasses will generally make API calls
   * to publish some version of the story into external systems.
   *
   * @return void
   * @task publish
   */
  abstract protected function publishFeedStory();


  /**
   * Enable or disable the worker. Normally, this checks configuration to
   * see if Phabricator is linked to applicable external systems.
   *
   * @return bool True if this worker should try to publish stories.
   * @task publish
   */
  abstract public function isEnabled();


/* -(  Story Context  )------------------------------------------------------ */


  /**
   * Get the @{class:PhabricatorFeedStory} that should be published.
   *
   * @return PhabricatorFeedStory The story to publish.
   * @task context
   */
  protected function getFeedStory() {
    if (!$this->feedStory) {
      $story = $this->loadFeedStory();
      $this->feedStory = $story;
    }
    return $this->feedStory;
  }


  /**
   * Get the viewer for the act of publishing.
   *
   * NOTE: Publishing currently uses the omnipotent viewer because it depends
   * on loading external accounts. Possibly we should tailor this. See T3732.
   * Using the actor for most operations might make more sense.
   *
   * @return PhabricatorUser Viewer.
   * @task context
   */
  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }


  /**
   * Get the @{class:DoorkeeperFeedStoryPublisher} which handles this object.
   *
   * @return DoorkeeperFeedStoryPublisher Object publisher.
   * @task context
   */
  protected function getPublisher() {
    return $this->publisher;
  }


  /**
   * Get the primary object the story is about, like a
   * @{class:DifferentialRevision} or @{class:ManiphestTask}.
   *
   * @return object Object which the story is about.
   * @task context
   */
  protected function getStoryObject() {
    if (!$this->storyObject) {
      $story = $this->getFeedStory();
      try {
        $object = $story->getPrimaryObject();
      } catch (Exception $ex) {
        throw new PhabricatorWorkerPermanentFailureException(
          $ex->getMessage());
      }
      $this->storyObject = $object;
    }
    return $this->storyObject;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Load the @{class:DoorkeeperFeedStoryPublisher} which corresponds to this
   * object. Publishers provide a common API for pushing object updates into
   * foreign systems.
   *
   * @return DoorkeeperFeedStoryPublisher Publisher for the story's object.
   * @task internal
   */
  private function loadPublisher() {
    $story = $this->getFeedStory();
    $viewer = $this->getViewer();
    $object = $this->getStoryObject();

    $publishers = id(new PhutilSymbolLoader())
      ->setAncestorClass('DoorkeeperFeedStoryPublisher')
      ->loadObjects();

    foreach ($publishers as $publisher) {
      if (!$publisher->canPublishStory($story, $object)) {
        continue;
      }

      $publisher
        ->setViewer($viewer)
        ->setFeedStory($story);

      $object = $publisher->willPublishStory($object);
      $this->storyObject = $object;

      $this->publisher = $publisher;
      break;
    }

    return $this->publisher;
  }


/* -(  Inherited  )---------------------------------------------------------- */


  /**
   * Doorkeeper workers set up some context, then call
   * @{method:publishFeedStory}.
   */
  final protected function doWork() {
    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $this->log("%s\n", pht('Phabricator is running in silent mode.'));
      return;
    }

    if (!$this->isEnabled()) {
      $this->log(
        "%s\n",
        pht("Doorkeeper worker '%s' is not enabled.", get_class($this)));
      return;
    }

    $publisher = $this->loadPublisher();
    if (!$publisher) {
      $this->log("%s\n", pht('Story is about an unsupported object type.'));
      return;
    } else {
      $this->log("%s\n", pht("Using publisher '%s'.", get_class($publisher)));
    }

    $this->publishFeedStory();
  }


  /**
   * By default, Doorkeeper workers perform a small number of retries with
   * exponential backoff. A consideration in this policy is that many of these
   * workers are laden with side effects.
   */
  public function getMaximumRetryCount() {
    return 4;
  }


  /**
   * See @{method:getMaximumRetryCount} for a description of Doorkeeper
   * retry defaults.
   */
  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    $count = $task->getFailureCount();
    return (5 * 60) * pow(8, $count);
  }

}

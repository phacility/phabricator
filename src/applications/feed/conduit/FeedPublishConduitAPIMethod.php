<?php

final class FeedPublishConduitAPIMethod extends FeedConduitAPIMethod {

  public function getAPIMethodName() {
    return 'feed.publish';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Publish a story to the feed.');
  }

  protected function defineParamTypes() {
    return array(
      'type' => 'required string',
      'data' => 'required dict',
      'time' => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'nonempty phid';
  }

  protected function execute(ConduitAPIRequest $request) {
    $type = $request->getValue('type');
    $data = $request->getValue('data');
    $time = $request->getValue('time');

    $author_phid = $request->getUser()->getPHID();
    $phids = array($author_phid);

    $publisher = new PhabricatorFeedStoryPublisher();
    $publisher->setStoryType($type);
    $publisher->setStoryData($data);
    $publisher->setStoryTime($time);
    $publisher->setRelatedPHIDs($phids);
    $publisher->setStoryAuthorPHID($author_phid);

    $data = $publisher->publish();

    return $data->getPHID();
  }

}

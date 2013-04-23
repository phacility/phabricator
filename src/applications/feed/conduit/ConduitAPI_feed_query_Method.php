<?php

/**
 * @group conduit
 */
final class ConduitAPI_feed_query_Method
  extends ConduitAPI_feed_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Query the feed for stories";
  }

  private function getDefaultLimit() {
    return 100;
  }

  public function defineParamTypes() {
    return array(
      'filterPHIDs' => 'optional list <phid>',
      'limit' => 'optional int (default '.$this->getDefaultLimit().')',
      'after' => 'optional int',
      'view' => 'optional string (data, html, html-summary, text)',
    );
  }

  private function getSupportedViewTypes() {
    return array(
        'html' => 'Full HTML presentation of story',
        'data' => 'Dictionary with various data of the story',
        'html-summary' => 'Story contains only the title of the story',
        'text' => 'Simple one-line plain text representation of story',
    );
  }

  public function defineErrorTypes() {

    $view_types = array_keys($this->getSupportedViewTypes());
    $view_types = implode(', ', $view_types);

    return array(
      'ERR-UNKNOWN-TYPE' =>
        'Unsupported view type, possibles are: ' . $view_types
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {

    $results = array();
    $user = $request->getUser();

    $view_type = $request->getValue('view');
    if (!$view_type) {
      $view_type = 'data';
    }

    $limit = $request->getValue('limit');
    if (!$limit) {
      $limit = $this->getDefaultLimit();
    }
    $filter_phids = $request->getValue('filterPHIDs');
    if (!$filter_phids) {
      $filter_phids = array();
    }
    $after = $request->getValue('after');

    $query = id(new PhabricatorFeedQuery())
      ->setLimit($limit)
      ->setFilterPHIDs($filter_phids)
      ->setViewer($user)
      ->setAfterID($after);
    $stories = $query->execute();

    if ($stories) {
      foreach ($stories as $story) {

        $story_data = $story->getStoryData();

        $data = null;

        $view = $story->renderView();
        $view->setEpoch($story->getEpoch());
        $view->setUser($user);

        switch ($view_type) {
          case 'html':
            $data = $view->render();
          break;
          case 'html-summary':
            $data = $view->render();
          break;
          case 'data':
            $data = array(
              'class' => $story_data->getStoryType(),
              'epoch' => $story_data->getEpoch(),
              'authorPHID' => $story_data->getAuthorPHID(),
              'chronologicalKey' => $story_data->getChronologicalKey(),
              'data' => $story_data->getStoryData(),
            );
          break;
          case 'text':
            $data = array(
              'class' => $story_data->getStoryType(),
              'epoch' => $story_data->getEpoch(),
              'authorPHID' => $story_data->getAuthorPHID(),
              'chronologicalKey' => $story_data->getChronologicalKey(),
              'text' => $story->renderText()
            );
          break;
          default:
            throw new ConduitException('ERR-UNKNOWN-TYPE');
        }

        $results[$story_data->getPHID()] = $data;
      }
    }

    return $results;
  }

}

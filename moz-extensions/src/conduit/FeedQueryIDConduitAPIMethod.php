<?php

/*
 * This file is a subclass of src/applications/feed/conduit/FeedQueryConduitAPIMethod.php
 * that was needed to give the ability to filter transactions based on ID values.
 */

final class FeedQueryIDConduitAPIMethod extends FeedQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'feed.query_id';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  private function getDefaultLimit() {
    return 100;
  }

  protected function defineParamTypes() {
    return array(
      'limit' => 'optional int (default '.$this->getDefaultLimit().')',
      'after' => 'optional int',
      'before' => 'optional int',
      'view' => 'optional string (data, html, html-summary, text)',
    );
  }

  public function execute(ConduitAPIRequest $request) {
    $results = array();

    $pager = id(new AphrontCursorPagerView());
    $user = $request->getUser();

    $view_type = $request->getValue('view');
    if (!$view_type) {
      $view_type = 'data';
    }

    $query = (new PhabricatorFeedIDQuery())
      ->setOrder('oldest')
      ->setViewer($user);

    $after = $request->getValue('after');
    if (strlen($after)) {
      $pager->setAfterID($after);
    }

    $before = $request->getValue('before');
    if (strlen($before)) {
      $pager->setBeforeID($before);
    }

    $limit = $request->getValue('limit');
    if (!$limit) {
      $limit = $this->getDefaultLimit();
    }
    $pager->setPageSize($limit);

    $stories = $query->executeWithCursorPager($pager);

    if ($stories) {
      foreach ($stories as $story) {

        $story_data = $story->getStoryData();

        $data = null;

        try {
          $view = $story->renderView();
        } catch (Exception $ex) {
          // When stories fail to render, just fail that story.
          phlog($ex);
          continue;
        }

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
              'id' => $story_data->getID(),
              'phid' => $story_data->getPHID(),
              'class' => $story_data->getStoryType(),
              'epoch' => $story_data->getEpoch(),
              'authorPHID' => $story_data->getAuthorPHID(),
              'chronologicalKey' => $story_data->getChronologicalKey(),
              'data' => $story_data->getStoryData(),
            );
          break;
          case 'text':
            $data = array(
              'id' => $story_data->getID(),
              'phid' => $story_data->getPHID(),
              'class' => $story_data->getStoryType(),
              'epoch' => $story_data->getEpoch(),
              'authorPHID' => $story_data->getAuthorPHID(),
              'chronologicalKey' => $story_data->getChronologicalKey(),
              'objectPHID' => $story->getPrimaryObjectPHID(),
              'text' => $story->renderText(),
            );
          break;
          default:
            throw new ConduitException('ERR-UNKNOWN-TYPE');
        }

        $results[] = $data;
      }
    }

    $result = array(
      'data' => $results,
    );

    return $this->addPagerResults($result, $pager);
  }
}


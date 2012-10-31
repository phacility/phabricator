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
 * @group conduit
 */
final class ConduitAPI_feed_query_Method extends ConduitAPIMethod {

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
      'view' => 'optional string (data, html, html-summary)',
    );
  }

  private function getSupportedViewTypes() {
    return array(
        'html' => 'Full HTML presentation of story',
        'data' => 'Dictionary with various data of the story',
        'html-summary' => 'Story contains only the title of the story',
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
      $handle_phids = array_mergev(mpull($stories, 'getRequiredHandlePHIDs'));
      $handles = id(new PhabricatorObjectHandleData($handle_phids))
        ->loadHandles();

      foreach ($stories as $story) {

        $story->setHandles($handles);

        $story_data = $story->getStoryData();

        $data = null;

        $view = $story->renderView();
        $view->setEpoch($story->getEpoch());
        $view->setViewer($user);

        switch ($view_type) {
          case 'html':
            $data = $view->render();
          break;
          case 'html-summary':
            $view->setOneLineStory(true);
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
          default:
            throw new ConduitException('ERR-UNKNOWN-TYPE');
        }

        $results[$story_data->getPHID()] = $data;
      }
    }

    return $results;
  }

}

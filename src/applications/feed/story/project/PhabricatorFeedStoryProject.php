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

class PhabricatorFeedStoryProject extends PhabricatorFeedStory {

  public function getRequiredHandlePHIDs() {
    return array(
      $this->getStoryData()->getAuthorPHID(),
      $this->getStoryData()->getValue('projectPHID'),
    );
  }

  public function getRequiredObjectPHIDs() {
    return array(
      $this->getStoryData()->getAuthorPHID(),
    );
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = new PhabricatorFeedStoryView();

    $type = $data->getValue('type');
    $old = $data->getValue('old');
    $new = $data->getValue('new');
    $proj = $this->getHandle($data->getValue('projectPHID'));

    $author_phid = $data->getAuthorPHID();
    $author = $this->getHandle($author_phid);

    switch ($type) {
      case PhabricatorProjectTransactionType::TYPE_NAME:
        if (strlen($old)) {
          $action = 'renamed project '.
            '<strong>'.$proj->renderLink().'</strong>'.
            ' from '.
            '<strong>'.phutil_escape_html($old).'</strong>'.
            ' to '.
            '<strong>'.phutil_escape_html($new).'</strong>.';
        } else {
          $action = 'created project '.
            '<strong>'.$proj->renderLink().'</strong>'.
            ' (as '.
            '<strong>'.phutil_escape_html($new).'</strong>).';
        }
        break;
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ((count($add) == 1) && (count($rem) == 0) &&
            (head($add) == $author_phid)) {
          $action = 'joined project <strong>'.$proj->renderLink().'</strong>.';
        } else if ((count($add) == 0) && (count($rem) == 1) &&
                   (head($rem) == $author_phid)) {
          $action = 'left project <strong>'.$proj->renderLink().'</strong>.';
        } else if (empty($rem)) {
          $action = 'added members to project '.
            '<strong>'.$proj->renderLink().'</strong>: '.
            $this->renderHandleList($add).'.';
        } else if (empty($add)) {
          $action = 'removed members from project '.
            '<strong>'.$proj->renderLink().'</strong>: '.
            $this->renderHandleList($rem).'.';
        } else {
          $action = 'changed members of project '.
            '<strong>'.$proj->renderLink().'</strong>, added: '.
            $this->renderHandleList($add).'; removed: '.
            $this->renderHandleList($rem).'.';
        }
        break;
      default:
        $action = 'updated project <strong>'.$proj->renderLink().'</strong>.';
        break;
    }
    $view->setTitle('<strong>'.$author->renderLink().'</strong> '.$action);
    $view->setOneLineStory(true);

    return $view;
  }

}

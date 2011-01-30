<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class DifferentialAddCommentView extends AphrontView {

  private $revision;
  private $actions;
  private $actionURI;

  public function setRevision($revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function setActionURI($uri) {
    $this->actionURI = $uri;
  }

  public function render() {

    $revision = $this->revision;

    $actions = array();
    foreach ($this->actions as $action) {
      $actions[$action] = DifferentialAction::getActionVerb($action);
    }

    $form = new AphrontFormView();
    $form
      ->setAction($this->actionURI)
      ->addHiddenInput('revision_id', $revision->getID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Action')
          ->setName('action')
          ->setOptions($actions))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('comment')
          ->setLabel('Comment'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Comment'));

    return
      '<div class="differential-panel">'.
        '<h1>Add Comment</h1>'.
        $form->render().
      '</div>';
  }
}

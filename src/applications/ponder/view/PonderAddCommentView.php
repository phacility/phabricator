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

final class PonderAddCommentView extends AphrontView {

  private $target;
  private $user;
  private $actionURI;
  private $questionID;

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setQuestionID($id) {
    $this->questionID = $id;
    return $this;
  }

  public function setActionURI($uri) {
    $this->actionURI = $uri;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-comment-table-css');

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $questionID = $this->questionID;
    $target = $this->target;

    $form = new AphrontFormView();
    $form
      ->setUser($this->user)
      ->setAction($this->actionURI)
      ->setWorkflow(true)
      ->addHiddenInput('target', $target)
      ->addHiddenInput('question_id', $questionID)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('content'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Editorialize'));

    $view = id(new AphrontMoreView())
      ->setSome(id(new AphrontNullView())->render())
      ->setMore($form->render())
      ->setExpandText('Add Comment');

    return $view->render();
  }
}

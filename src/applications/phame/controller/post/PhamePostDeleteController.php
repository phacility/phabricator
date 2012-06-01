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
 * @group phame
 */
final class PhamePostDeleteController
extends PhameController {

  private $phid;

  private function setPostPHID($phid) {
    $this->phid = $phid;
    return $this;
  }
  private function getPostPHID() {
    return $this->phid;
  }

  protected function getSideNavFilter() {
    return 'post/delete/'.$this->getPostPHID();
  }

  protected function getSideNavExtraPostFilters() {
    $filters = array(
      array('key'  => $this->getSideNavFilter(),
            'name' => 'Delete Post')
    );

    return $filters;
  }

  public function willProcessRequest(array $data) {
    $phid = $data['phid'];
    $this->setPostPHID($phid);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user    = $request->getUser();
    $post    = id(new PhamePost())->loadOneWhere(
      'phid = %s',
      $this->getPostPHID());
    if (empty($post)) {
      return new Aphront404Response();
    }
    if ($post->getBloggerPHID() != $user->getPHID()) {
      return new Aphront403Response();
    }
    $edit_uri = $post->getEditURI();

    if ($request->isFormPost()) {
      $post->delete();
      return id(new AphrontRedirectResponse())->setURI('/phame/?deleted');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Delete post?')
      ->appendChild('Really delete this post? It will be gone forever.')
      ->addSubmitButton('Delete')
      ->addCancelButton($edit_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}

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
final class PhameBlogDeleteController
extends PhameController {

  private $phid;

  private function setBlogPHID($phid) {
    $this->phid = $phid;
    return $this;
  }
  private function getBlogPHID() {
    return $this->phid;
  }

  protected function getSideNavFilter() {
    return 'blog/delete/'.$this->getBlogPHID();
  }

  protected function getSideNavExtraBlogFilters() {
    $filters = array(
      array('key'  => $this->getSideNavFilter(),
            'name' => 'Delete Blog')
    );

    return $filters;
  }

  public function willProcessRequest(array $data) {
    $phid = $data['phid'];
    $this->setBlogPHID($phid);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withPHIDs(array($this->getBlogPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$blog) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $blog->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/phame/blog/?deleted');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Delete blog?')
      ->appendChild('Really delete this blog? It will be gone forever.')
      ->addSubmitButton('Delete')
      ->addCancelButton($blog->getEditURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}

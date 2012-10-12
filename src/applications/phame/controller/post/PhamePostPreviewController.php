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
final class PhamePostPreviewController
extends PhameController {

  protected function getSideNavFilter() {
    return null;
  }

  public function processRequest() {
    $request     = $this->getRequest();
    $user        = $request->getUser();
    $body        = $request->getStr('body');
    $title       = $request->getStr('title');
    $phame_title = $request->getStr('phame_title');

    $post = id(new PhamePost())
      ->setBody($body)
      ->setTitle($title)
      ->setPhameTitle($phame_title)
      ->setDateModified(time());

    $blogger = PhabricatorObjectHandleData::loadOneHandle($user->getPHID());

    $post_html = id(new PhamePostDetailView())
      ->setUser($user)
      ->setBlogger($blogger)
      ->setPost($post)
      ->setIsPreview(true)
      ->render();

    return id(new AphrontAjaxResponse())->setContent($post_html);
  }
}

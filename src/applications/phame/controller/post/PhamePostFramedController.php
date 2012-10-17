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
final class PhamePostFramedController extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    $blog = $post->getBlog();

    $phame_request = $request->setPath('/post/'.$post->getPhameTitle());
    $skin = $post->getBlog()->getSkinRenderer($phame_request);

    $uri = clone $request->getRequestURI();
    $uri->setPath('/phame/live/'.$blog->getID().'/');

    $skin
      ->setPreview(true)
      ->setBlog($post->getBlog())
      ->setBaseURI((string)$uri);

    $response = $skin->processRequest();
    $response->setFrameable(true);
    return $response;
  }
}

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
final class PhameBlogLiveController extends PhameController {

  private $id;
  private $more;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->more = idx($data, 'more', '');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    if ($blog->getDomain() && ($request->getHost() != $blog->getDomain())) {
      return id(new AphrontRedirectResponse())
        ->setURI('http://'.$blog->getDomain().'/'.$this->more);
    }

    $phame_request = clone $request;
    $phame_request->setPath('/'.ltrim($this->more, '/'));

    if ($blog->getDomain()) {
      $uri = new PhutilURI('http://'.$blog->getDomain().'/');
    } else {
      $uri = '/phame/live/'.$blog->getID().'/';
      $uri = PhabricatorEnv::getURI($uri);
    }

    $skin = $blog->getSkinRenderer($phame_request);
    $skin
      ->setBlog($blog)
      ->setBaseURI((string)$uri);

    $skin->willProcessRequest(array());
    return $skin->processRequest();
  }

}

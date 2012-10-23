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
final class PhamePostNotLiveController extends PhameController {

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
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    $reasons = array();
    if (!$post->getBlog()) {
      $reasons[] =
        '<p>'.pht('You can not view the live version of this post because it '.
        'is not associated with a blog. Move the post to a blog in order to '.
        'view it live.').'</p>';
    }

    if ($post->isDraft()) {
      $reasons[] =
        '<p>'.pht('You can not view the live version of this post because it '.
        'is still a draft. Use "Preview/Publish" to publish the post.').'</p>';
    }

    if ($reasons) {
      $cancel_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Post Not Live'))
        ->addCancelButton($cancel_uri);

      foreach ($reasons as $reason) {
        $dialog->appendChild($reason);
      }

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    // No reason this can't go live, maybe an old link. Kick them live and see
    // what happens.

    $blog = $post->getBlog();
    $live_uri = 'http://'.$blog->getDomain().'/'.$post->getPhameTitle();

    return id(new AphrontRedirectResponse())->setURI($live_uri);
  }
}

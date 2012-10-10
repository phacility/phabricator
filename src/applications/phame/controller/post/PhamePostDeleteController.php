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

  public function willProcessRequest(array $data) {
    $phid = $data['phid'];
    $this->setPostPHID($phid);
  }

  public function processRequest() {
    $request   = $this->getRequest();
    $user      = $request->getUser();
    $post_phid = $this->getPostPHID();
    $posts     = id(new PhamePostQuery())
      ->withPHIDs(array($post_phid))
      ->execute();
    $post      = reset($posts);
    if (empty($post)) {
      return new Aphront404Response();
    }
    if ($post->getBloggerPHID() != $user->getPHID()) {
      return new Aphront403Response();
    }
    $post_noun = $post->getHumanName();

    if ($request->isFormPost()) {
      $edge_type = PhabricatorEdgeConfig::TYPE_POST_HAS_BLOG;
      $edges     = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array($post_phid))
        ->withEdgeTypes(array($edge_type))
        ->execute();

      $blog_edges = $edges[$post_phid][$edge_type];
      $blog_phids = array_keys($blog_edges);
      $editor     = id(new PhabricatorEdgeEditor())
        ->setActor($user);
      foreach ($blog_phids as $phid) {
        $editor->removeEdge($post_phid, $edge_type, $phid);
      }
      $editor->save();

      $post->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/phame/'.$post_noun.'/?deleted');
    }

    $edit_uri = $post->getEditURI();
    $dialog   = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Delete '.$post_noun.'?')
      ->appendChild('Really delete this '.$post_noun.'? '.
                    'It will be gone forever.')
      ->addSubmitButton('Delete')
      ->addCancelButton($edit_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}

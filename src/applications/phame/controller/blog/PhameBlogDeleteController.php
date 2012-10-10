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
    $blogger_edge_type = PhabricatorEdgeConfig::TYPE_BLOG_HAS_BLOGGER;
    $post_edge_type    = PhabricatorEdgeConfig::TYPE_BLOG_HAS_POST;
    $request           = $this->getRequest();
    $user              = $request->getUser();
    $blog_phid         = $this->getBlogPHID();
    $blogs             = id(new PhameBlogQuery())
                           ->withPHIDs(array($blog_phid))
                           ->execute();
    $blog              = reset($blogs);
    if (empty($blog)) {
      return new Aphront404Response();
    }

    $phids      = array($blog_phid);
    $edge_types = array(
      PhabricatorEdgeConfig::TYPE_BLOG_HAS_BLOGGER,
      PhabricatorEdgeConfig::TYPE_BLOG_HAS_POST,
    );

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($phids)
      ->withEdgeTypes($edge_types)
      ->execute();

    $blogger_edges = $edges[$blog_phid][$blogger_edge_type];
    // TODO -- make this check use a policy
    if (!isset($blogger_edges[$user->getPHID()]) &&
        !$user->isAdmin()) {
      return new Aphront403Response();
    }

    $edit_uri = $blog->getEditURI();

    if ($request->isFormPost()) {
      $blogger_phids = array_keys($blogger_edges);
      $post_edges    = $edges[$blog_phid][$post_edge_type];
      $post_phids    = array_keys($post_edges);
      $editor        = id(new PhabricatorEdgeEditor())
        ->setActor($user);
      foreach ($blogger_phids as $phid) {
        $editor->removeEdge($blog_phid, $blogger_edge_type, $phid);
      }
      foreach ($post_phids as $phid) {
        $editor->removeEdge($blog_phid, $post_edge_type, $phid);
      }
      $editor->save();

      $blog->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/phame/blog/?deleted');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Delete blog?')
      ->appendChild('Really delete this blog? It will be gone forever.')
      ->addSubmitButton('Delete')
      ->addCancelButton($edit_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}

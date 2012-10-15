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

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // NOTE: We're loading with the logged-out user so we can raise the right
    // error if the blog permissions aren't set correctly.

    $blog = null;
    $policy_exception = null;

    try {
      $blog = id(new PhameBlogQuery())
        ->setViewer(new PhabricatorUser())
        ->withIDs(array($this->id))
        ->executeOne();
    } catch (PhabricatorPolicyException $ex) {
      $policy_exception = $ex;
    }

    if (!$blog && !$policy_exception) {
      return new Aphront404Response();
    }

    $errors = array();
    if ($policy_exception) {
      $errors[] = pht('"Visible To" must be set to "Public".');
    }

    if ($blog && !$blog->getDomain()) {
      $errors[] = pht('You must configure a custom domain.');
    }

    if ($errors) {
      if ($blog) {
        $cancel_uri = $this->getApplicationURI('/blog/view/'.$blog->getID());
      } else {
        $cancel_uri = $this->getApplicationURI();
      }

      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->addCancelButton($cancel_uri)
        ->setTitle(pht('Live Blog Unavailable'));

      foreach ($errors as $error) {
        $dialog->appendChild('<p>'.$error.'</p>');
      }

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if ($request->getHost() != $blog->getDomain()) {
      $uri = 'http://'.$blog->getDomain().'/';
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $posts = id(new PhamePostQuery())
      ->setViewer($user)
      ->withBlogPHIDs(array($blog->getPHID()))
      ->executeWithCursorPager($pager);

    $skin = $blog->getSkinRenderer();
    $skin
      ->setUser($user)
      ->setPosts($posts)
      ->setBloggers($this->loadViewerHandles(mpull($posts, 'getBloggerPHID')))
      ->setBlog($blog)
      ->setRequestURI($this->getRequest()->getRequestURI());

    $page = $this->buildStandardPageView();
    $page->appendChild($skin);
    $page->setShowChrome(false);

    $response = new AphrontWebpageResponse();
    $response->setContent($page->render());
    return $response;
  }

}

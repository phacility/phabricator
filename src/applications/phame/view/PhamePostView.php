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
final class PhamePostView extends AphrontView {

  private $post;
  private $author;
  private $viewer;
  private $body;
  private $skin;
  private $summary;


  public function setSkin(PhameBlogSkin $skin) {
    $this->skin = $skin;
    return $this;
  }

  public function getSkin() {
    return $this->skin;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setAuthor(PhabricatorObjectHandle $author) {
    $this->author = $author;
    return $this;
  }

  public function getAuthor() {
    return $this->author;
  }

  public function setPost(PhamePost $post) {
    $this->post = $post;
    return $this;
  }

  public function getPost() {
    return $this->post;
  }

  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  public function getBody() {
    return $this->body;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function renderTitle() {
    $href = $this->getSkin()->getURI('post/'.$this->getPost()->getPhameTitle());
    return phutil_render_tag(
      'h2',
      array(
        'class' => 'phame-post-title',
      ),
      phutil_render_tag(
        'a',
        array(
          'href' => $href,
        ),
        phutil_escape_html($this->getPost()->getTitle())));
  }

  public function renderDatePublished() {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phame-post-date',
      ),
      phutil_escape_html(
        pht(
          'Published on %s by %s',
          phabricator_datetime(
            $this->getPost()->getDatePublished(),
            $this->getViewer()),
          $this->getAuthor()->getName())));
  }

  public function renderBody() {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phame-post-body',
      ),
      $this->getBody());
  }

  public function renderSummary() {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phame-post-body',
      ),
      $this->getSummary());
  }

  public function renderComments() {
    $post = $this->getPost();

    switch ($post->getCommentsWidget()) {
      case 'facebook':
        $comments = $this->renderFacebookComments();
        break;
      case 'disqus':
        $comments = $this->renderDisqusComments();
        break;
      case 'none':
      default:
        $comments = null;
        break;
    }
    return $comments;
  }

  public function render() {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phame-post',
      ),
      $this->renderTitle().
      $this->renderDatePublished().
      $this->renderBody().
      $this->renderComments());
  }

  public function renderWithSummary() {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phame-post',
      ),
      $this->renderTitle().
      $this->renderDatePublished().
      $this->renderSummary());
  }

  private function renderFacebookComments() {
    $fb_id = PhabricatorEnv::getEnvConfig('facebook.application-id');
    if (!$fb_id) {
      return null;
    }

    $fb_root = phutil_render_tag('div',
      array(
        'id' => 'fb-root',
      ),
      ''
    );

    $c_uri = '//connect.facebook.net/en_US/all.js#xfbml=1&appId='.$fb_id;
    $fb_js = jsprintf(
      '<script>(function(d, s, id) {'.
      ' var js, fjs = d.getElementsByTagName(s)[0];'.
      ' if (d.getElementById(id)) return;'.
      ' js = d.createElement(s); js.id = id;'.
      ' js.src = %s;'.
      ' fjs.parentNode.insertBefore(js, fjs);'.
      '}(document, \'script\', \'facebook-jssdk\'));</script>',
      $c_uri
    );


    $uri = $this->getSkin()->getURI('post/'.$this->getPost()->getPhameTitle());

    $fb_comments = phutil_render_tag('div',
      array(
        'class'            => 'fb-comments',
        'data-href'        => $uri,
        'data-num-posts'   => 5,
      ),
      ''
    );

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phame-comments-facebook',
      ),
      $fb_root.
      $fb_js.
      $fb_comments);
  }

  private function renderDisqusComments() {
    $disqus_shortname = PhabricatorEnv::getEnvConfig('disqus.shortname');
    if (!$disqus_shortname) {
      return null;
    }

    $post = $this->getPost();

    $disqus_thread = phutil_render_tag('div',
      array(
        'id' => 'disqus_thread'
      )
    );

    // protip - try some  var disqus_developer = 1; action to test locally
    $disqus_js = jsprintf(
      '<script>'.
      ' var disqus_shortname = "phabricator";'.
      ' var disqus_identifier = %s;'.
      ' var disqus_url = %s;'.
      ' var disqus_title = %s;'.
      '(function() {'.
      ' var dsq = document.createElement("script");'.
      ' dsq.type = "text/javascript";'.
      ' dsq.async = true;'.
      ' dsq.src = "http://" + disqus_shortname + ".disqus.com/embed.js";'.
      '(document.getElementsByTagName("head")[0] ||'.
      ' document.getElementsByTagName("body")[0]).appendChild(dsq);'.
      '})(); </script>',
      $post->getPHID(),
      $this->getSkin()->getURI('post/'.$this->getPost()->getPhameTitle()),
      $post->getTitle()
    );

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phame-comments-disqus',
      ),
      $disqus_thread.
      $disqus_js);
  }

}

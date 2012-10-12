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
final class PhamePostDetailView extends AphrontView {

  private $user;
  private $post;
  private $blog;
  private $blogger;
  private $actions = array();
  private $requestURI;
  private $isPreview;
  private $showComments;
  private $shouldShorten;

  public function setShouldShorten($should_shorten) {
    $this->shouldShorten = $should_shorten;
    return $this;
  }
  public function getShouldShorten() {
    return $this->shouldShorten;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  public function getUser() {
    return $this->user;
  }

  public function setPost(PhamePost $post) {
    $this->post = $post;
    return $this;
  }
  private function getPost() {
    return $this->post;
  }

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }
  private function getBlog() {
    return $this->blog;
  }

  public function setBlogger(PhabricatorObjectHandle $blogger) {
    $this->blogger = $blogger;
    return $this;
  }
  private function getBlogger() {
    return $this->blogger;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }
  private function getActions() {
    if ($this->actions) {
      return $this->actions;
    }
    return array();
  }

  public function setRequestURI(PhutilURI $uri) {
    $uri = PhabricatorEnv::getProductionURI($uri->setQueryParams(array()));
    $this->requestURI = $uri;
    return $this;
  }
  private function getRequestURI() {
    return $this->requestURI;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }
  private function isPreview() {
    return $this->isPreview;
  }

  public function setShowComments($show_comments) {
    $this->showComments = $show_comments;
    return $this;
  }
  private function getShowComments() {
    return $this->showComments;
  }

  public function render() {
    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('phame-css');

    $user    = $this->getUser();
    $blogger = $this->getBlogger();
    $post    = $this->getPost();
    $actions = $this->getActions();
    $noun    = $post->isDraft() ? 'Draft' : 'Post';
    $buttons = array();

    foreach ($actions as $action) {
      switch ($action) {
        case 'view':
          $uri   = $post->getViewURI($blogger->getName());
          $label = 'View '.$noun;
          break;
        case 'edit':
          $uri   = $post->getEditURI();
          $label = 'Edit '.$noun;
          break;
        case 'more':
          if ($post->isDraft()) {
            $uri   = '/phame/draft/';
            $label = 'Back to Your Drafts';
          } else {
            $uri   = '/phame/posts/'.$blogger->getName().'/';
            $label = 'More Posts by '.phutil_escape_html($blogger->getName());
          }
          break;
        default:
          break;
      }
      $button = phutil_render_tag(
        'a',
        array(
          'href'  => $uri,
          'class' => 'grey button',
        ),
        $label);
      $buttons[] = $button;
    }

    $button_html = '';
    if ($buttons) {
      $button_html = phutil_render_tag(
        'div',
        array(
          'class' => 'buttons'
        ),
        implode('', $buttons)
      );
    }

    $publish_date = $post->getDatePublished();
    if ($publish_date) {
      $caption = 'Published '.
        phabricator_datetime($publish_date,
                             $user);
    } else {
      $caption = 'Last edited '.
        phabricator_datetime($post->getDateModified(),
                             $user);
    }
    $caption .= ' by <b>'.
      phutil_escape_html($blogger->getName()).'</b>.';

    $shortened = false;
    $body_text = $post->getBody();
    if ($this->getShouldShorten()) {
      $body_length = phutil_utf8_strlen($body_text);
      $body_text   = phutil_utf8_shorten($body_text, 5000);
      $shortened   = $body_length > phutil_utf8_strlen($body_text);
    }
    $engine = PhabricatorMarkupEngine::newPhameMarkupEngine();
    $body   = $engine->markupText($body_text);

    $comments = null;
    if ($this->getShowComments()) {
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
    }

    $more_to_do = null;
    if (!$comments) {

      if ($shortened) {
        $more_to_do =
          phutil_render_tag(
            'div',
            array(
              'class' => 'more-and-comments'
            ),
            phutil_render_tag(
              'a',
              array(
              'href' => $post->getViewURI()
            ),
            '&#8594; Read More'
          )
        );

      } else if ($post->getCommentsWidget() &&
                 $post->getCommentsWidget() != 'none') {
         $more_to_do =
          phutil_render_tag(
            'div',
            array(
              'class' => 'more-and-comments'
            ),
            phutil_render_tag(
              'a',
              array(
              'href' => $post->getViewURI()
            ),
            '&#8594; Comment'
          )
        );
      }
    }

    $post_html =
      phutil_render_tag(
        'div',
        array(
          'class' => 'blog-post'
        ),
        phutil_render_tag(
          'div',
          array(
            'class' => 'header',
          ),
          $button_html .
          phutil_render_tag(
            'h1',
            array(),
            phutil_render_tag('a',
              array(
                'href' => $post->getViewURI($blogger->getName())
              ),
              phutil_escape_html($post->getTitle())
            )
          ).
          phutil_render_tag(
            'div',
            array(
              'class' => 'last-updated'
            ),
            $caption
          )
        ).
        phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup'
          ),
          $body
        ).
        $comments.$more_to_do
      );

    return $post_html;
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

    $fb_comments = phutil_render_tag('div',
      array(
        'class'            => 'fb-comments',
        'data-href'        => $this->getRequestURI(),
        'data-num-posts'   => 5,
        'data-width'       => 1080,  // we hack this to fluid in css
        'data-colorscheme' => 'dark',
      ),
      ''
    );

    return '<hr />' . $fb_root . $fb_js . $fb_comments;
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
      $this->getRequestURI(),
      $post->getTitle()
    );

    return '<hr />' . $disqus_thread . $disqus_js;
  }

}

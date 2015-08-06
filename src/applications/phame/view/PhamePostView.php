<?php

final class PhamePostView extends AphrontView {

  private $post;
  private $author;
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
    return phutil_tag(
      'h2',
      array(
        'class' => 'phame-post-title',
      ),
      phutil_tag(
        'a',
        array(
          'href' => $href,
        ),
        $this->getPost()->getTitle()));
  }

  public function renderDatePublished() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post-date',
      ),
        pht(
          'Published on %s by %s',
          phabricator_datetime(
            $this->getPost()->getDatePublished(),
            $this->getUser()),
          $this->getAuthor()->getName()));
  }

  public function renderBody() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post-body',
      ),
      $this->getBody());
  }

  public function renderSummary() {
    return phutil_tag(
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
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post',
      ),
      array(
        $this->renderTitle(),
        $this->renderDatePublished(),
        $this->renderBody(),
        $this->renderComments(),
      ));
  }

  public function renderWithSummary() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post',
      ),
      array(
        $this->renderTitle(),
        $this->renderDatePublished(),
        $this->renderSummary(),
      ));
  }

  private function renderFacebookComments() {
    $fb_id = PhabricatorFacebookAuthProvider::getFacebookApplicationID();
    if (!$fb_id) {
      return null;
    }

    $fb_root = phutil_tag('div',
      array(
        'id' => 'fb-root',
      ),
      '');

    $c_uri = '//connect.facebook.net/en_US/all.js#xfbml=1&appId='.$fb_id;
    $fb_js = CelerityStaticResourceResponse::renderInlineScript(
      jsprintf(
        '(function(d, s, id) {'.
        ' var js, fjs = d.getElementsByTagName(s)[0];'.
        ' if (d.getElementById(id)) return;'.
        ' js = d.createElement(s); js.id = id;'.
        ' js.src = %s;'.
        ' fjs.parentNode.insertBefore(js, fjs);'.
        '}(document, \'script\', \'facebook-jssdk\'));',
        $c_uri));


    $uri = $this->getSkin()->getURI('post/'.$this->getPost()->getPhameTitle());

    require_celerity_resource('phame-css');
    $fb_comments = phutil_tag('div',
      array(
        'class'            => 'fb-comments',
        'data-href'        => $uri,
        'data-num-posts'   => 5,
      ),
      '');

    return phutil_tag(
      'div',
      array(
        'class' => 'phame-comments-facebook',
      ),
      array(
        $fb_root,
        $fb_js,
        $fb_comments,
      ));
  }

  private function renderDisqusComments() {
    $disqus_shortname = PhabricatorEnv::getEnvConfig('disqus.shortname');
    if (!$disqus_shortname) {
      return null;
    }

    $post = $this->getPost();

    $disqus_thread = phutil_tag('div',
      array(
        'id' => 'disqus_thread',
      ));

    // protip - try some  var disqus_developer = 1; action to test locally
    $disqus_js = CelerityStaticResourceResponse::renderInlineScript(
      jsprintf(
        ' var disqus_shortname = %s;'.
        ' var disqus_identifier = %s;'.
        ' var disqus_url = %s;'.
        ' var disqus_title = %s;'.
        '(function() {'.
        ' var dsq = document.createElement("script");'.
        ' dsq.type = "text/javascript";'.
        ' dsq.async = true;'.
        ' dsq.src = "//" + disqus_shortname + ".disqus.com/embed.js";'.
        '(document.getElementsByTagName("head")[0] ||'.
        ' document.getElementsByTagName("body")[0]).appendChild(dsq);'.
        '})();',
        $disqus_shortname,
        $post->getPHID(),
        $this->getSkin()->getURI('post/'.$this->getPost()->getPhameTitle()),
        $post->getTitle()));

    return phutil_tag(
      'div',
      array(
        'class' => 'phame-comments-disqus',
      ),
      array(
        $disqus_thread,
        $disqus_js,
      ));
  }

}

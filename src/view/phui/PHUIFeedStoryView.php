<?php

final class PHUIFeedStoryView extends AphrontView {

  private $title;
  private $image;
  private $imageHref;
  private $appIcon;
  private $phid;
  private $epoch;
  private $viewed;
  private $href;
  private $pontification = null;
  private $tokenBar = array();
  private $projects = array();
  private $actions = array();
  private $chronologicalKey;
  private $tags;
  private $authorIcon;

  public function setTags($tags) {
    $this->tags = $tags;
    return $this;
  }

  public function getTags() {
    return $this->tags;
  }

  public function setChronologicalKey($chronological_key) {
    $this->chronologicalKey = $chronological_key;
    return $this;
  }

  public function getChronologicalKey() {
    return $this->chronologicalKey;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function getImage() {
    return $this->image;
  }

  public function setImageHref($image_href) {
    $this->imageHref = $image_href;
    return $this;
  }

  public function setAppIcon($icon) {
    $this->appIcon = $icon;
    return $this;
  }

  public function setViewed($viewed) {
    $this->viewed = $viewed;
    return $this;
  }

  public function getViewed() {
    return $this->viewed;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setAuthorIcon($author_icon) {
    $this->authorIcon = $author_icon;
    return $this;
  }

  public function getAuthorIcon() {
    return $this->authorIcon;
  }

  public function setTokenBar(array $tokens) {
    $this->tokenBar = $tokens;
    return $this;
  }

  public function addProject($project) {
    $this->projects[] = $project;
    return $this;
  }

  public function addAction(PHUIIconView $action) {
    $this->actions[] = $action;
    return $this;
  }

  public function setPontification($text, $title = null) {
    if ($title) {
      $title = phutil_tag('h3', array(), $title);
    }
    $copy = phutil_tag(
      'div',
        array(
          'class' => 'phui-feed-story-bigtext-post',
        ),
        array(
          $title,
          $text,
        ));
    $this->appendChild($copy);
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function renderNotification($user) {
    $classes = array(
      'phabricator-notification',
    );

    if (!$this->viewed) {
      $classes[] = 'phabricator-notification-unread';
    }
    if ($this->epoch) {
      if ($user) {
        $foot = phabricator_datetime($this->epoch, $user);
        $foot = phutil_tag(
          'span',
          array(
            'class' => 'phabricator-notification-date',
          ),
          $foot);
      } else {
        $foot = null;
      }
    } else {
      $foot = pht('No time specified.');
    }

    return javelin_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
        'sigil' => 'notification',
        'meta' => array(
          'href' => $this->getHref(),
        ),
      ),
      array($this->title, $foot));
  }

  public function render() {

    require_celerity_resource('phui-feed-story-css');
    Javelin::initBehavior('phui-hovercards');

    $body = null;
    $foot = null;

    $actor = new PHUIIconView();
    $actor->addClass('phui-feed-story-actor');

    $author_icon = $this->getAuthorIcon();

    if ($this->image) {
      $actor->addClass('phui-feed-story-actor-image');
      $actor->setImage($this->image);
    } else if ($author_icon) {
      $actor->addClass('phui-feed-story-actor-icon');
      $actor->setIcon($author_icon);
    }

    if ($this->imageHref) {
      $actor->setHref($this->imageHref);
    }

    if ($this->epoch) {
      // TODO: This is really bad; when rendering through Conduit and via
      // renderText() we don't have a user.
      if ($this->hasViewer()) {
        $foot = phabricator_datetime($this->epoch, $this->getViewer());
      } else {
        $foot = null;
      }
    } else {
      $foot = pht('No time specified.');
    }

    if ($this->chronologicalKey) {
      $foot = phutil_tag(
        'a',
        array(
          'href' => '/feed/'.$this->chronologicalKey.'/',
        ),
        $foot);
    }

    $icon = null;
    if ($this->appIcon) {
      $icon = id(new PHUIIconView())
        ->setIcon($this->appIcon);
    }

    $action_list = array();
    $icons = null;
    foreach ($this->actions as $action) {
      $action_list[] = phutil_tag(
        'li',
          array(
            'class' => 'phui-feed-story-action-item',
          ),
          $action);
    }
    if (!empty($action_list)) {
      $icons = phutil_tag(
        'ul',
          array(
            'class' => 'phui-feed-story-action-list',
          ),
          $action_list);
    }

    $head = phutil_tag(
      'div',
      array(
        'class' => 'phui-feed-story-head',
      ),
      array(
        $actor,
        nonempty($this->title, pht('Untitled Story')),
        $icons,
      ));

    if (!empty($this->tokenBar)) {
      $tokenview = phutil_tag(
        'div',
          array(
            'class' => 'phui-feed-token-bar',
          ),
        $this->tokenBar);
      $this->appendChild($tokenview);
    }

    $body_content = $this->renderChildren();
    if ($body_content) {
      $body = phutil_tag(
        'div',
        array(
          'class' => 'phui-feed-story-body phabricator-remarkup',
        ),
        $body_content);
    }

    $tags = null;
    if ($this->tags) {
      $tags = array(
        " \xC2\xB7 ",
        $this->tags,
      );
    }

    $foot = phutil_tag(
      'div',
      array(
        'class' => 'phui-feed-story-foot',
      ),
      array(
        $icon,
        $foot,
        $tags,
      ));

    $classes = array('phui-feed-story');

    return id(new PHUIBoxView())
      ->addClass(implode(' ', $classes))
      ->setBorder(true)
      ->addMargin(PHUI::MARGIN_MEDIUM_BOTTOM)
      ->appendChild(array($head, $body, $foot));
  }

}

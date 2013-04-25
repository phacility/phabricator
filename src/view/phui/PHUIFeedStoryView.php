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

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
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
          $text));
    $this->appendChild($copy);
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function renderNotification() {
    $classes = array(
      'phabricator-notification',
    );

    if (!$this->viewed) {
      $classes[] = 'phabricator-notification-unread';
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
      $this->title);
  }

  public function render() {

    require_celerity_resource('phui-feed-story-css');

    $actor = '';
    if ($this->image) {
      $actor = new PHUIIconView();
      $actor->setImage($this->image);
      $actor->addClass('phui-feed-story-actor-image');
      if ($this->imageHref) {
        $actor->setHref($this->imageHref);
      }
    }

    $action_list = array();
    $icons = null;
    foreach ($this->actions as $action) {
      $action_list[] = phutil_tag(
        'li',
          array(
          'class' => 'phui-feed-story-action-item'
        ),
        $action);
    }
    if (!empty($action_list)) {
      $icons = phutil_tag(
        'ul',
          array(
            'class' => 'phui-feed-story-action-list'
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
        $icons
      ));

    $body = null;
    $foot = null;
    $image_style = null;

    if (!empty($this->tokenBar)) {
      $tokenview = phutil_tag(
        'div',
          array(
            'class' => 'phui-feed-token-bar'
          ),
        $this->tokenBar);
      $this->appendChild($tokenview);
    }

    $body_content = $this->renderChildren();
    if ($body_content) {
      $body = phutil_tag(
        'div',
        array(
          'class' => 'phui-feed-story-body',
        ),
        $body_content);
    }

    if ($this->epoch) {
      $foot = phabricator_datetime($this->epoch, $this->user);
    } else {
      $foot = pht('No time specified.');
    }

    $icon = null;
    if ($this->appIcon) {
      $icon = new PHUIIconView();
      $icon->setSpriteIcon($this->appIcon);
      $icon->setSpriteSheet(PHUIIconView::SPRITE_APPS);
    }

    $foot = phutil_tag(
      'div',
      array(
        'class' => 'phui-feed-story-foot',
      ),
      array(
        $icon,
        $foot));

    return id(new PHUIBoxView())
      ->addClass('phui-feed-story')
      ->setShadow(true)
      ->addMargin(PHUI::MARGIN_MEDIUM_BOTTOM)
      ->appendChild(array($head, $body, $foot));
  }
}

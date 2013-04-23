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


    $actor = '';
    if ($this->image) {
      $actor = new PHUIIconView();
      $actor->setImage($this->image);
      if ($this->imageHref) {
        $actor->setHref($this->imageHref);
      }
    }

    $head = phutil_tag(
      'div',
      array(
        'class' => 'phui-feed-story-head',
      ),
      array(
        $actor,
        nonempty($this->title, pht('Untitled Story')),
      ));

    $body = null;
    $foot = null;
    $image_style = null;
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

    require_celerity_resource('phui-feed-story-css');

    $story = phutil_tag(
      'div',
        array(
          'class' => 'phui-feed-story',
          'style' => $image_style,
        ),
        array(
          $head,
          $body,
          $foot));

    return phutil_tag(
      'div',
        array(
          'class' => 'phui-feed-wrap'
        ),
        $story);
  }
}

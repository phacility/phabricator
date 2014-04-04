<?php

final class PHUITimelineEventView extends AphrontView {

  const DELIMITER = " \xC2\xB7 ";

  private $userHandle;
  private $title;
  private $icon;
  private $color;
  private $classes = array();
  private $contentSource;
  private $dateCreated;
  private $anchor;
  private $isEditable;
  private $isEdited;
  private $transactionPHID;
  private $isPreview;
  private $eventGroup = array();
  private $hideByDefault;
  private $token;
  private $tokenRemoved;

  public function setHideByDefault($hide_by_default) {
    $this->hideByDefault = $hide_by_default;
    return $this;
  }

  public function getHideByDefault() {
    return $this->hideByDefault;
  }

  public function setTransactionPHID($transaction_phid) {
    $this->transactionPHID = $transaction_phid;
    return $this;
  }

  public function getTransactionPHID() {
    return $this->transactionPHID;
  }

  public function setIsEdited($is_edited) {
    $this->isEdited = $is_edited;
    return $this;
  }

  public function getIsEdited() {
    return $this->isEdited;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }

  public function getIsPreview() {
    return $this->isPreview;
  }

  public function setIsEditable($is_editable) {
    $this->isEditable = $is_editable;
    return $this;
  }

  public function getIsEditable() {
    return $this->isEditable;
  }

  public function setDateCreated($date_created) {
    $this->dateCreated = $date_created;
    return $this;
  }

  public function getDateCreated() {
    return $this->dateCreated;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function setUserHandle(PhabricatorObjectHandle $handle) {
    $this->userHandle = $handle;
    return $this;
  }

  public function setAnchor($anchor) {
    $this->anchor = $anchor;
    return $this;
  }

  public function getAnchor() {
    return $this->anchor;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function setToken($token, $removed=false) {
    $this->token = $token;
    $this->tokenRemoved = $removed;
    return $this;
  }

  public function getEventGroup() {
    return array_merge(array($this), $this->eventGroup);
  }

  public function addEventToGroup(PHUITimelineEventView $event) {
    $this->eventGroup[] = $event;
    return $this;
  }

  protected function renderEventTitle($is_first_event, $force_icon) {
    $title = $this->title;
    if (($title === null) && !$this->hasChildren()) {
      $title = '';
    }

    if ($is_first_event) {
      $extra = array();
      $is_first_extra = true;
      foreach ($this->getEventGroup() as $event) {
        $extra[] = $event->renderExtra($is_first_extra);
        $is_first_extra = false;
      }
      $extra = array_reverse($extra);
      $extra = array_mergev($extra);
      $extra = javelin_tag(
        'span',
        array(
          'sigil' => 'timeline-extra',
          'class' => 'phui-timeline-extra',
        ),
        phutil_implode_html(self::DELIMITER, $extra));
    } else {
      $extra = null;
    }

    if ($title !== null || $extra) {
      $title_classes = array();
      $title_classes[] = 'phui-timeline-title';

      $icon = null;
      if ($this->icon || $force_icon) {
        $title_classes[] = 'phui-timeline-title-with-icon';
      }

      if ($this->icon) {
        $fill_classes = array();
        $fill_classes[] = 'phui-timeline-icon-fill';
        if ($this->color) {
          $fill_classes[] = 'phui-timeline-icon-fill-'.$this->color;
        }

        $icon = phutil_tag(
          'span',
          array(
            'class' => implode(' ', $fill_classes),
          ),
          phutil_tag(
            'span',
            array(
              'class' => 'phui-timeline-icon sprite-icons '.
                         'icons-'.$this->icon.'-white',
            ),
            ''));
      }

      $token = null;
      if ($this->token) {
        $token = id(new PHUIIconView())
          ->addClass('phui-timeline-token')
          ->setSpriteSheet(PHUIIconView::SPRITE_TOKENS)
          ->setSpriteIcon($this->token);
        if ($this->tokenRemoved) {
          $token->addClass('strikethrough');
        }
      }

      $title = phutil_tag(
        'div',
        array(
          'class' => implode(' ', $title_classes),
        ),
        array($icon, $token, $title, $extra));
    }

    return $title;
  }

  public function render() {

    $events = $this->getEventGroup();

    // Move events with icons first.
    $icon_keys = array();
    foreach ($this->getEventGroup() as $key => $event) {
      if ($event->icon) {
        $icon_keys[] = $key;
      }
    }
    $events = array_select_keys($events, $icon_keys) + $events;
    $force_icon = (bool)$icon_keys;

    $group_titles = array();
    $group_children = array();
    $is_first_event = true;
    foreach ($events as $event) {
      $group_titles[] = $event->renderEventTitle($is_first_event, $force_icon);
      $is_first_event = false;
      if ($event->hasChildren()) {
        $group_children[] = $event->renderChildren();
      }
    }

    $wedge = phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-wedge phui-timeline-border',
      ),
      '');

    $image_uri = $this->userHandle->getImageURI();
    $image = phutil_tag(
      'div',
      array(
        'style' => 'background-image: url('.$image_uri.')',
        'class' => 'phui-timeline-image',
      ),
      '');

    $content_classes = array();
    $content_classes[] = 'phui-timeline-content';

    $classes = array();
    $classes[] = 'phui-timeline-event-view';
    if ($group_children) {
      $classes[] = 'phui-timeline-major-event';
      $content = phutil_tag(
        'div',
        array(
          'class' => 'phui-timeline-inner-content',
        ),
        array(
          $group_titles,
          phutil_tag(
            'div',
            array(
              'class' => 'phui-timeline-core-content',
            ),
            $group_children),
        ));
    } else {
      $classes[] = 'phui-timeline-minor-event';
      $content = $group_titles;
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-group phui-timeline-border',
      ),
      $content);

    $content = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $content_classes),
      ),
      array($image, $wedge, $content));

    $outer_classes = $this->classes;
    $outer_classes[] = 'phui-timeline-shell';
    $color = null;
    foreach ($this->getEventGroup() as $event) {
      if ($event->color) {
        $color = $event->color;
        break;
      }
    }

    if ($color) {
      $outer_classes[] = 'phui-timeline-'.$color;
    }

    $sigil = null;
    $meta = null;
    if ($this->getTransactionPHID()) {
      $sigil = 'transaction';
      $meta = array(
        'phid' => $this->getTransactionPHID(),
        'anchor' => $this->anchor,
      );
    }

    return javelin_tag(
      'div',
      array(
        'class' => implode(' ', $outer_classes),
        'id' => $this->anchor ? 'anchor-'.$this->anchor : null,
        'sigil' => $sigil,
        'meta' => $meta,
      ),
      phutil_tag(
        'div',
        array(
          'class' => implode(' ', $classes),
        ),
        $content));
  }

  private function renderExtra($is_first_extra) {
    $extra = array();

    if ($this->getIsPreview()) {
      $extra[] = pht('PREVIEW');
    } else {
      $xaction_phid = $this->getTransactionPHID();

      if ($this->getIsEdited()) {
        $extra[] = javelin_tag(
          'a',
          array(
            'href'  => '/transactions/history/'.$xaction_phid.'/',
            'sigil' => 'workflow transaction-edit-history',
          ),
          pht('Edited'));
      }

      if ($this->getIsEditable()) {
        $extra[] = javelin_tag(
          'a',
          array(
            'href'  => '/transactions/edit/'.$xaction_phid.'/',
            'sigil' => 'workflow transaction-edit',
          ),
          pht('Edit'));
      }

      if ($is_first_extra) {
        $source = $this->getContentSource();
        if ($source) {
          $extra[] = id(new PhabricatorContentSourceView())
            ->setContentSource($source)
            ->setUser($this->getUser())
            ->render();
        }

        $date_created = null;
        foreach ($this->getEventGroup() as $event) {
          if ($event->getDateCreated()) {
            if ($date_created === null) {
              $date_created = $event->getDateCreated();
            } else {
              $date_created = min($event->getDateCreated(), $date_created);
            }
          }
        }

        if ($date_created) {
          $date = phabricator_datetime(
            $this->getDateCreated(),
            $this->getUser());
          if ($this->anchor) {
            Javelin::initBehavior('phabricator-watch-anchor');

            $anchor = id(new PhabricatorAnchorView())
              ->setAnchorName($this->anchor)
              ->render();

            $date = array(
              $anchor,
              phutil_tag(
                'a',
                array(
                  'href' => '#'.$this->anchor,
                ),
                $date),
            );
          }
          $extra[] = $date;
        }
      }
    }

    return $extra;
  }

}

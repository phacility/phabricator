<?php

/**
 * The default one-for-all hovercard. We may derive from this one to create
 * more specialized ones.
 */
final class PhabricatorHovercardView extends AphrontView {

  /**
   * @var PhabricatorObjectHandle
   */
  private $handle;
  private $object;

  private $title = array();
  private $detail;
  private $tags = array();
  private $fields = array();
  private $actions = array();
  private $badges = array();

  public function setObjectHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setDetail($detail) {
    $this->detail = $detail;
    return $this;
  }

  public function addField($label, $value) {
    $this->fields[] = array(
      'label' => $label,
      'value' => $value,
    );
    return $this;
  }

  public function addAction($label, $uri, $workflow = false) {
    $this->actions[] = array(
      'label'    => $label,
      'uri'      => $uri,
      'workflow' => $workflow,
    );
    return $this;
  }

  public function addTag(PHUITagView $tag) {
    $this->tags[] = $tag;
    return $this;
  }

  public function addBadge(PHUIBadgeMiniView $badge) {
    $this->badges[] = $badge;
    return $this;
  }

  public function render() {
    if (!$this->handle) {
      throw new PhutilInvalidStateException('setObjectHandle');
    }

    $viewer = $this->getUser();
    $handle = $this->handle;

    require_celerity_resource('phabricator-hovercard-view-css');

    $title = array(
      id(new PHUISpacesNamespaceContextView())
        ->setUser($viewer)
        ->setObject($this->getObject()),
      pht(
        '%s: %s',
        $handle->getTypeName(),
        $this->title ? $this->title : $handle->getName()),
    );

    $header = new PHUIHeaderView();
    $header->setHeader($title);
    if ($this->tags) {
      foreach ($this->tags as $tag) {
        $header->addTag($tag);
      }
    }

    $body = array();

    if ($this->detail) {
      $body_title = $this->detail;
    } else {
      // Fallback for object handles
      $body_title = $handle->getFullName();
    }

    $body[] = phutil_tag_div('phabricator-hovercard-body-header', $body_title);

    foreach ($this->fields as $field) {
      $item = array(
        phutil_tag('strong', array(), $field['label']),
        ': ',
        phutil_tag('span', array(), $field['value']),
      );
      $body[] = phutil_tag_div('phabricator-hovercard-body-item', $item);
    }

    if ($this->badges) {
      $badges = id(new PHUIBadgeBoxView())
        ->addItems($this->badges)
        ->setCollapsed(true);
      $body[] = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-hovercard-body-item hovercard-badges',
        ),
        $badges);
    }

    if ($handle->getImageURI()) {
      // Probably a user, we don't need to assume something else
      // "Prepend" the image by appending $body
      $body = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-hovercard-body-image',
        ),
        phutil_tag(
          'div',
          array(
            'class' => 'profile-header-picture-frame',
            'style' => 'background-image: url('.$handle->getImageURI().');',
          ),
          ''))
      ->appendHTML(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-hovercard-body-details',
          ),
          $body));
    }

    $buttons = array();

    foreach ($this->actions as $action) {
      $options = array(
        'class' => 'button grey',
        'href'  => $action['uri'],
      );

      if ($action['workflow']) {
        $options['sigil'] = 'workflow';
        $buttons[] = javelin_tag(
          'a',
          $options,
          $action['label']);
      } else {
        $buttons[] = phutil_tag(
          'a',
          $options,
          $action['label']);
      }
    }

    $tail = null;
    if ($buttons) {
      $tail = phutil_tag_div('phabricator-hovercard-tail', $buttons);
    }

    $hovercard = phutil_tag_div(
      'phabricator-hovercard-container',
      array(
        phutil_tag_div('phabricator-hovercard-head', $header),
        phutil_tag_div('phabricator-hovercard-body grouped', $body),
        $tail,
      ));

    return phutil_tag_div('phabricator-hovercard-wrapper', $hovercard);
  }

}

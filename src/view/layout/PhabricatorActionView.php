<?php

final class PhabricatorActionView extends AphrontView {

  private $name;
  private $icon;
  private $iconSheet;
  private $href;
  private $disabled;
  private $workflow;
  private $renderAsForm;
  private $download;
  private $objectURI;
  private $sigils = array();

  public function setObjectURI($object_uri) {
    $this->objectURI = $object_uri;
    return $this;
  }
  public function getObjectURI() {
    return $this->objectURI;
  }

  public function setDownload($download) {
    $this->download = $download;
    return $this;
  }

  public function getDownload() {
    return $this->download;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function addSigil($sigil) {
    $this->sigils[] = $sigil;
    return $this;
  }

  /**
   * If the user is not logged in and the action is relatively complicated,
   * give them a generic login link that will re-direct to the page they're
   * viewing.
   */
  public function getHref() {
    if (($this->workflow || $this->renderAsForm) && !$this->download) {
      if (!$this->user || !$this->user->isLoggedIn()) {
        return id(new PhutilURI('/auth/start/'))
          ->setQueryParam('next', (string)$this->getObjectURI());
      }
    }

    return $this->href;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setIconSheet($sheet) {
    $this->iconSheet = $sheet;
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function setRenderAsForm($form) {
    $this->renderAsForm = $form;
    return $this;
  }

  public function render() {

    $icon = null;
    if ($this->icon) {
      $sheet = nonempty($this->iconSheet, PHUIIconView::SPRITE_ICONS);

      $suffix = '';
      if ($this->disabled) {
        $suffix = '-grey';
      }

      $icon = id(new PHUIIconView())
        ->addClass('phabricator-action-view-icon')
        ->setSpriteIcon($this->icon.$suffix)
        ->setSpriteSheet($sheet);
    }

    if ($this->href) {

      $sigils = array();
      if ($this->workflow) {
        $sigils[] = 'workflow';
      }
      if ($this->download) {
        $sigils[] = 'download';
      }

      if ($this->sigils) {
        $sigils = array_merge($sigils, $this->sigils);
      }

      $sigils = $sigils ? implode(' ', $sigils) : null;

      if ($this->renderAsForm) {
        if (!$this->user) {
          throw new Exception(
            'Call setUser() when rendering an action as a form.');
        }

        $item = javelin_tag(
          'button',
          array(
            'class' => 'phabricator-action-view-item',
          ),
          $this->name);

        $item = phabricator_form(
          $this->user,
          array(
            'action'    => $this->getHref(),
            'method'    => 'POST',
            'sigil'     => $sigils,
          ),
          $item);
      } else {
        $item = javelin_tag(
          'a',
          array(
            'href'  => $this->getHref(),
            'class' => 'phabricator-action-view-item',
            'sigil' => $sigils,
          ),
          $this->name);
      }
    } else {
      $item = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-action-view-item',
        ),
        $this->name);
    }

    $classes = array();
    $classes[] = 'phabricator-action-view';
    if ($this->disabled) {
      $classes[] = 'phabricator-action-view-disabled';
    }

    return phutil_tag(
      'li',
      array(
        'class' => implode(' ', $classes),
      ),
      array($icon, $item));
  }

  public static function getAvailableIcons() {
    $manifest = PHUIIconView::getSheetManifest(PHUIIconView::SPRITE_ICONS);

    $results = array();
    $prefix = 'icons-';
    foreach ($manifest as $sprite) {
      $name = $sprite['name'];
      if (preg_match('/-(white|grey)$/', $name)) {
        continue;
      }
      if (!strncmp($name, $prefix, strlen($prefix))) {
        $results[] = substr($name, strlen($prefix));
      }
    }

    return $results;
  }

}

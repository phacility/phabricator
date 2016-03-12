<?php

final class PhabricatorActionView extends AphrontView {

  private $name;
  private $icon;
  private $href;
  private $disabled;
  private $label;
  private $workflow;
  private $renderAsForm;
  private $download;
  private $sigils = array();
  private $metadata;
  private $selected;
  private $openInNewWindow;

  public function setSelected($selected) {
    $this->selected = $selected;
    return $this;
  }

  public function getSelected() {
    return $this->selected;
  }

  public function setMetadata($metadata) {
    $this->metadata = $metadata;
    return $this;
  }

  public function getMetadata() {
    return $this->metadata;
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

  public function getHref() {
    return $this->href;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setLabel($label) {
    $this->label = $label;
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

  public function setOpenInNewWindow($open_in_new_window) {
    $this->openInNewWindow = $open_in_new_window;
    return $this;
  }

  public function getOpenInNewWindow() {
    return $this->openInNewWindow;
  }

  public function render() {

    $icon = null;
    if ($this->icon) {
      $color = '';
      if ($this->disabled) {
        $color = ' grey';
      }
      $icon = id(new PHUIIconView())
        ->addClass('phabricator-action-view-icon')
        ->setIcon($this->icon.$color);
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
        if (!$this->hasViewer()) {
          throw new Exception(
            pht(
              'Call %s when rendering an action as a form.',
              'setViewer()'));
        }

        $item = javelin_tag(
          'button',
          array(
            'class' => 'phabricator-action-view-item',
          ),
          array($icon, $this->name));

        $item = phabricator_form(
          $this->getViewer(),
          array(
            'action'    => $this->getHref(),
            'method'    => 'POST',
            'sigil'     => $sigils,
            'meta'      => $this->metadata,
          ),
          $item);
      } else {
        if ($this->getOpenInNewWindow()) {
          $target = '_blank';
        } else {
          $target = null;
        }

        $item = javelin_tag(
          'a',
          array(
            'href'  => $this->getHref(),
            'class' => 'phabricator-action-view-item',
            'target' => $target,
            'sigil' => $sigils,
            'meta' => $this->metadata,
          ),
          array($icon, $this->name));
      }
    } else {
      $item = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-action-view-item',
        ),
        array($icon, $this->name));
    }

    $classes = array();
    $classes[] = 'phabricator-action-view';

    if ($this->disabled) {
      $classes[] = 'phabricator-action-view-disabled';
    }

    if ($this->label) {
      $classes[] = 'phabricator-action-view-label';
    }

    if ($this->selected) {
      $classes[] = 'phabricator-action-view-selected';
    }

    return phutil_tag(
      'li',
      array(
        'class' => implode(' ', $classes),
      ),
      $item);
  }

}

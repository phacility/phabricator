<?php

final class PhabricatorActionView extends AphrontView {

  private $name;
  private $icon;
  private $href;
  private $disabled;
  private $workflow;
  private $renderAsForm;
  private $download;

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

  public function setIcon($icon) {
    $this->icon = $icon;
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

      $suffix = '';
      if ($this->disabled) {
        $suffix = '-grey';
      }

      require_celerity_resource('sprite-icon-css');
      $icon = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-action-view-icon sprite-icon '.
                       'action-'.$this->icon.$suffix,
        ),
        '');
    }

    if ($this->href) {
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

        $sigils = array();
        if ($this->workflow) {
          $sigils[] = 'workflow';
        }
        if ($this->download) {
          $sigils[] = 'download';
        }

        $item = phabricator_form(
          $this->user,
          array(
            'action'    => $this->href,
            'method'    => 'POST',
            'sigil'     => implode(' ', $sigils),
          ),
          $item);
      } else {
        $item = javelin_tag(
          'a',
          array(
            'href'  => $this->href,
            'class' => 'phabricator-action-view-item',
            'sigil' => $this->workflow ? 'workflow' : null,
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
    $root = dirname(phutil_get_library_root('phabricator'));
    $path = $root.'/resources/sprite/manifest/icon.json';
    $data = Filesystem::readFile($path);
    $manifest = json_decode($data, true);

    $results = array();
    $prefix = 'action-';
    foreach ($manifest['sprites'] as $sprite) {
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

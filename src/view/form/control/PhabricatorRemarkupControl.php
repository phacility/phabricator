<?php

final class PhabricatorRemarkupControl extends AphrontFormTextAreaControl {
  private $disableMacro = false;

  private $disableFullScreen = false;

  public function setDisableMacros($disable) {
    $this->disableMacro = $disable;
    return $this;
  }

  public function setDisableFullScreen($disable) {
    $this->disableFullScreen = $disable;
    return $this;
  }

  protected function renderInput() {
    $id = $this->getID();
    if (!$id) {
      $id = celerity_generate_unique_node_id();
      $this->setID($id);
    }

    $viewer = $this->getUser();
    if (!$viewer) {
      throw new PhutilInvalidStateException('setUser');
    }

    // We need to have this if previews render images, since Ajax can not
    // currently ship JS or CSS.
    require_celerity_resource('lightbox-attachment-css');

    if (!$this->getDisabled()) {
      Javelin::initBehavior(
        'aphront-drag-and-drop-textarea',
        array(
          'target' => $id,
          'activatedClass' => 'aphront-textarea-drag-and-drop',
          'uri' => '/file/dropupload/',
          'chunkThreshold' => PhabricatorFileStorageEngine::getChunkThreshold(),
        ));
    }

    $root_id = celerity_generate_unique_node_id();

    $user_datasource = new PhabricatorPeopleDatasource();
    $proj_datasource = id(new PhabricatorProjectDatasource())
      ->setParameters(
        array(
          'autocomplete' => 1,
        ));

    Javelin::initBehavior(
      'phabricator-remarkup-assist',
      array(
        'pht' => array(
          'bold text' => pht('bold text'),
          'italic text' => pht('italic text'),
          'monospaced text' => pht('monospaced text'),
          'List Item' => pht('List Item'),
          'Quoted Text' => pht('Quoted Text'),
          'data' => pht('data'),
          'name' => pht('name'),
          'URL' => pht('URL'),
        ),
        'disabled' => $this->getDisabled(),
        'rootID' => $root_id,
        'autocompleteMap' => (object)array(
          64 => array( // "@"
            'datasourceURI' => $user_datasource->getDatasourceURI(),
            'headerIcon' => 'fa-user',
            'headerText' => pht('Find User:'),
            'hintText' => $user_datasource->getPlaceholderText(),
          ),
          35 => array( // "#"
            'datasourceURI' => $proj_datasource->getDatasourceURI(),
            'headerIcon' => 'fa-briefcase',
            'headerText' => pht('Find Project:'),
            'hintText' => $proj_datasource->getPlaceholderText(),
          ),
        ),
      ));
    Javelin::initBehavior('phabricator-tooltips', array());

    $actions = array(
      'fa-bold' => array(
        'tip' => pht('Bold'),
        'nodevice' => true,
      ),
      'fa-italic' => array(
        'tip' => pht('Italics'),
        'nodevice' => true,
      ),
      'fa-text-width' => array(
        'tip' => pht('Monospaced'),
        'nodevice' => true,
      ),
      'fa-link' => array(
        'tip' => pht('Link'),
        'nodevice' => true,
      ),
      array(
        'spacer' => true,
        'nodevice' => true,
      ),
      'fa-list-ul' => array(
        'tip' => pht('Bulleted List'),
        'nodevice' => true,
      ),
      'fa-list-ol' => array(
        'tip' => pht('Numbered List'),
        'nodevice' => true,
      ),
      'fa-code' => array(
        'tip' => pht('Code Block'),
        'nodevice' => true,
      ),
      'fa-quote-right' => array(
        'tip' => pht('Quote'),
        'nodevice' => true,
      ),
      'fa-table' => array(
        'tip' => pht('Table'),
        'nodevice' => true,
      ),
      'fa-cloud-upload' => array(
        'tip' => pht('Upload File'),
      ),
    );

    $can_use_macros =
      (!$this->disableMacro) &&
      (function_exists('imagettftext'));

    if ($can_use_macros) {
      $can_use_macros = PhabricatorApplication::isClassInstalledForViewer(
        'PhabricatorMacroApplication',
        $viewer);
    }

    if ($can_use_macros) {
      $actions[] = array(
        'spacer' => true,
        );
      $actions['fa-meh-o'] = array(
        'tip' => pht('Meme'),
      );
    }

    $actions['fa-eye'] = array(
      'tip' => pht('Preview'),
      'align' => 'right',
    );

    $actions[] = array(
      'spacer' => true,
      'align' => 'right',
    );

    $actions['fa-life-bouy'] = array(
      'tip' => pht('Help'),
      'align' => 'right',
      'href'  => PhabricatorEnv::getDoclink('Remarkup Reference'),
    );


    if (!$this->disableFullScreen) {
      $actions[] = array(
        'spacer' => true,
        'align' => 'right',
      );

      $actions['fa-arrows-alt'] = array(
        'tip' => pht('Fullscreen Mode'),
        'align' => 'right',
      );
    }

    $buttons = array();
    foreach ($actions as $action => $spec) {

      $classes = array();

      if (idx($spec, 'align') == 'right') {
        $classes[] = 'remarkup-assist-right';
      }

      if (idx($spec, 'nodevice')) {
        $classes[] = 'remarkup-assist-nodevice';
      }

      if (idx($spec, 'spacer')) {
        $classes[] = 'remarkup-assist-separator';
        $buttons[] = phutil_tag(
          'span',
          array(
            'class' => implode(' ', $classes),
          ),
          '');
        continue;
      } else {
        $classes[] = 'remarkup-assist-button';
      }

      $href = idx($spec, 'href', '#');
      if ($href == '#') {
        $meta = array('action' => $action);
        $mustcapture = true;
        $target = null;
      } else {
        $meta = array();
        $mustcapture = null;
        $target = '_blank';
      }

      $content = null;

      $tip = idx($spec, 'tip');
      if ($tip) {
        $meta['tip'] = $tip;
        $content = javelin_tag(
          'span',
          array(
            'aural' => true,
          ),
          $tip);
      }

      $sigils = array();
      $sigils[] = 'remarkup-assist';
      if (!$this->getDisabled()) {
        $sigils[] = 'has-tooltip';
      }

      $buttons[] = javelin_tag(
        'a',
        array(
          'class'       => implode(' ', $classes),
          'href'        => $href,
          'sigil'       => implode(' ', $sigils),
          'meta'        => $meta,
          'mustcapture' => $mustcapture,
          'target'      => $target,
          'tabindex'    => -1,
        ),
        phutil_tag(
          'div',
          array(
            'class' =>
              'remarkup-assist phui-icon-view phui-font-fa bluegrey '.$action,
          ),
          $content));
    }

    $buttons = phutil_tag(
      'div',
      array(
        'class' => 'remarkup-assist-bar',
      ),
      $buttons);

    $monospaced_textareas = null;
    $monospaced_textareas_class = null;

    $monospaced_textareas = $viewer
      ->loadPreferences()
      ->getPreference(
        PhabricatorUserPreferences::PREFERENCE_MONOSPACED_TEXTAREAS);
    if ($monospaced_textareas == 'enabled') {
      $monospaced_textareas_class = 'PhabricatorMonospaced';
    }

    $this->setCustomClass(
      'remarkup-assist-textarea '.$monospaced_textareas_class);

    return javelin_tag(
      'div',
      array(
        'sigil' => 'remarkup-assist-control',
        'class' => $this->getDisabled() ? 'disabled-control' : null,
        'id' => $root_id,
      ),
      array(
        $buttons,
        parent::renderInput(),
      ));
  }

}

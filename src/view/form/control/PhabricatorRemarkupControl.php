<?php

final class PhabricatorRemarkupControl extends AphrontFormTextAreaControl {
  private $disableMacro = false;

  public function setDisableMacros($disable) {
    $this->disableMacro = $disable;
    return $this;
  }

  protected function renderInput() {
    $id = $this->getID();
    if (!$id) {
      $id = celerity_generate_unique_node_id();
      $this->setID($id);
    }

    // We need to have this if previews render images, since Ajax can not
    // currently ship JS or CSS.
    require_celerity_resource('lightbox-attachment-css');

    Javelin::initBehavior(
      'aphront-drag-and-drop-textarea',
      array(
        'target'          => $id,
        'activatedClass'  => 'aphront-textarea-drag-and-drop',
        'uri'             => '/file/dropupload/',
      ));

    Javelin::initBehavior('phabricator-remarkup-assist', array());
    Javelin::initBehavior('phabricator-tooltips', array());

    $actions = array(
      'b'     => array(
        'tip' => pht('Bold'),
      ),
      'i'     => array(
        'tip' => pht('Italics'),
      ),
      'tt'    => array(
        'tip' => pht('Monospaced'),
      ),
      array(
        'spacer' => true,
      ),
      'ul' => array(
        'tip' => pht('Bulleted List'),
      ),
      'ol' => array(
        'tip' => pht('Numbered List'),
      ),
      'code' => array(
        'tip' => pht('Code Block'),
      ),
      'table' => array(
        'tip' => pht('Table'),
      )
    );
    if (!$this->disableMacro and function_exists('imagettftext')) {
      $actions[] = array(
        'spacer' => true,
        );
      $actions['meme'] = array(
        'tip' => pht('Meme'),
      );
    }
    $actions['help'] = array(
        'tip' => pht('Help'),
        'align' => 'right',
        'href'  => PhabricatorEnv::getDoclink(
          'article/Remarkup_Reference.html'),
      );

    $buttons = array();
    foreach ($actions as $action => $spec) {
      if (idx($spec, 'spacer')) {
        $buttons[] = phutil_tag(
          'span',
          array(
            'class' => 'remarkup-assist-separator',
          ),
          '');
        continue;
      }
      $classes = array();
      $classes[] = 'remarkup-assist-button';
      if (idx($spec, 'align') == 'right') {
        $classes[] = 'remarkup-assist-right';
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

      $tip = idx($spec, 'tip');
      if ($tip) {
        $meta['tip'] = $tip;
      }

      require_celerity_resource('sprite-icon-css');

      $buttons[] = javelin_tag(
        'a',
        array(
          'class'       => implode(' ', $classes),
          'href'        => $href,
          'sigil'       => 'remarkup-assist has-tooltip',
          'meta'        => $meta,
          'mustcapture' => $mustcapture,
          'target'      => $target,
          'tabindex'    => -1,
        ),
        phutil_tag(
          'div',
          array(
            'class' => 'remarkup-assist sprite-icon remarkup-assist-'.$action,
          ),
          ''));
    }

    $buttons = phutil_tag(
      'div',
      array(
        'class' => 'remarkup-assist-bar',
      ),
      $buttons);

    $monospaced_textareas = null;
    $monospaced_textareas_class = null;
    $user = $this->getUser();

    if ($user) {
      $monospaced_textareas = $user
        ->loadPreferences()
        ->getPreference(
          PhabricatorUserPreferences::PREFERENCE_MONOSPACED_TEXTAREAS);
      if ($monospaced_textareas == 'enabled') {
        $monospaced_textareas_class = 'PhabricatorMonospaced';
      }
    }

    $this->setCustomClass(
      'remarkup-assist-textarea '.$monospaced_textareas_class);

    return javelin_tag(
      'div',
      array(
        'sigil' => 'remarkup-assist-control',
      ),
      array(
        $buttons,
        parent::renderInput(),
      ));
  }

}

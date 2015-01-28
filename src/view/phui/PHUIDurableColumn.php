<?php

final class PHUIDurableColumn extends AphrontTagView {

  protected function getTagAttributes() {
    return array(
      'id' => 'durable-column',
      'class' => 'phui-durable-column',
    );
  }

  protected function getTagContent() {
    Javelin::initBehavior('durable-column');

    $classes = array();
    $classes[] = 'phui-durable-column-header';
    $classes[] = 'sprite-main-header';
    $classes[] = 'main-header-'.PhabricatorEnv::getEnvConfig('ui.header-color');

    $header = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      phutil_tag(
        'div',
        array(
          'class' => 'phui-durable-column-header-text',
        ),
        pht('Column Prototype')));

    $icon_bar = phutil_tag(
      'div',
      array(
        'class' => 'phui-durable-column-icon-bar',
      ),
      null); // <-- TODO: Icon buttons go here.

    $copy = pht(
      'This is a very early prototype of a persistent column. It is not '.
      'expected to work yet, and leaving it open will activate other new '.
      'features which will break things. Press "\\" (backslash) on your '.
      'keyboard to close it now.');

    $content = phutil_tag(
      'div',
      array(
        'class' => 'phui-durable-column-main',
      ),
      phutil_tag(
        'div',
        array(
          'id' => 'phui-durable-column-content',
          'class' => 'phui-durable-column-frame',
        ),
        phutil_tag(
          'div',
          array(
            'class' => 'phui-durable-column-content',
          ),
          $copy)));

    $input = phutil_tag(
      'textarea',
      array(
        'class' => 'phui-durable-column-textarea',
        'placeholder' => pht('Box for text...'),
      ));

    $footer = phutil_tag(
      'div',
      array(
        'class' => 'phui-durable-column-footer',
      ),
      array(
        phutil_tag(
          'button',
          array(
            'class' => 'grey',
          ),
          pht('Send')),
        phutil_tag(
          'div',
          array(
            'class' => 'phui-durable-column-status',
          ),
          pht('Status Text')),
      ));

    return array(
      $header,
      phutil_tag(
        'div',
        array(
          'class' => 'phui-durable-column-body',
        ),
        array(
          $icon_bar,
          $content,
          $input,
          $footer,
        )),
    );
  }

}

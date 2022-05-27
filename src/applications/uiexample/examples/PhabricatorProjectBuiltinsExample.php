<?php

final class PhabricatorProjectBuiltinsExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Project Builtin Images');
  }

  public function getDescription() {
    return pht('Builtin Project Images.');
  }

  public function getCategory() {
    return pht('Catalogs');
  }

  public function renderExample() {
    $viewer = $this->getRequest()->getUser();

    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/projects/v3/';

    Javelin::initBehavior('phabricator-tooltips', array());

    $map = array();
    $builtin_map = id(new FileFinder($root))
      ->withType('f')
      ->withFollowSymlinks(true)
      ->find();

    $images = array();
    foreach ($builtin_map as $image) {
      $file = PhabricatorFile::loadBuiltin($viewer, 'projects/v3/'.$image);
      $images[$file->getPHID()] = array(
        'uri' => $file->getBestURI(),
        'tip' => 'v3/'.$image,
      );
    }

    $buttons = array();
    foreach ($images as $phid => $spec) {
      $button = javelin_tag(
        'img',
        array(
          'height' => 100,
          'width' => 100,
          'src' => $spec['uri'],
          'style' => 'float: left; padding: 4px;',
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $spec['tip'],
            'size' => 300,
          ),
        ));

      $buttons[] = $button;
    }

    $wrap1 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Images'))
      ->appendChild($buttons)
      ->addClass('grouped');

    return phutil_tag(
      'div',
        array(),
        array(
          $wrap1,
        ));
  }
}

<?php

final class PhabricatorApplicationLaunchView extends AphrontTagView {

  private $application;

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  protected function getTagName() {
    return $this->application ? 'a' : 'div';
  }

  protected function getTagAttributes() {
    $application = $this->application;
    return array(
      'class' => array('phabricator-application-launch-container'),
      'href'  => $application ? $application->getBaseURI() : null,
    );
  }

  protected function getTagContent() {
    $application = $this->application;

    require_celerity_resource('phabricator-application-launch-view-css');

    $content = array();
    $icon = null;
    if ($application) {
      $content[] = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-application-launch-name',
        ),
        $application->getName());

      $content[] = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-application-launch-description',
        ),
        $application->getShortDescription());

      $classes = array();
      $classes[] = 'phabricator-application-launch-icon';

      $styles = array();
      $classes[] = $application->getIcon();
      $classes[] = 'phui-icon-view';
      $classes[] = 'phui-font-fa';

      $icon = phutil_tag(
        'span',
        array(
          'class' => implode(' ', $classes),
          'style' => nonempty(implode('; ', $styles), null),
        ),
        '');
    }

    return array(
      $icon,
      $content,
    );
  }

}

<?php

final class DarkConsoleXHProfPlugin extends DarkConsolePlugin {

  protected $profileFilePHID;

  public function getName() {
    return pht('XHProf');
  }

  public function getColor() {
    $data = $this->getData();
    if ($data['profileFilePHID']) {
      return '#ff00ff';
    }
    return null;
  }

  public function getDescription() {
    return pht('Provides detailed PHP profiling information through XHProf.');
  }

  public function generateData() {
    return array(
      'profileFilePHID' => $this->profileFilePHID,
      'profileURI' => (string)$this
        ->getRequestURI()
        ->alter('__profile__', 'page'),
    );
  }

  public function getXHProfRunID() {
    return $this->profileFilePHID;
  }

  public function renderPanel() {
    $data = $this->getData();

    $run = $data['profileFilePHID'];
    $profile_uri = $data['profileURI'];

    if (!DarkConsoleXHProfPluginAPI::isProfilerAvailable()) {
      $href = PhabricatorEnv::getDoclink('Installation Guide');
      $install_guide = phutil_tag(
        'a',
        array(
          'href' => $href,
          'class' => 'bright-link',
        ),
        pht('Installation Guide'));
      return hsprintf(
        '<div class="dark-console-no-content">%s</div>',
        pht(
          'The "xhprof" PHP extension is not available. Install xhprof '.
          'to enable the XHProf console plugin. You can find instructions in '.
          'the %s.',
          $install_guide));
    }

    $result = array();

    $header = phutil_tag(
      'div',
      array('class' => 'dark-console-panel-header'),
      array(
        phutil_tag(
          'a',
          array(
            'href'  => $profile_uri,
            'class' => $run ? 'disabled button' : 'green button',
          ),
          pht('Profile Page')),
        phutil_tag('h1', array(), pht('XHProf Profiler')),
      ));
    $result[] = $header;

    if ($run) {
      $result[] = phutil_tag(
        'a',
        array(
          'href' => "/xhprof/profile/$run/",
          'class' => 'bright-link',
          'style' => 'float: right; margin: 1em 2em 0 0; font-weight: bold;',
          'target' => '_blank',
        ),
        pht('Profile Permalink'));
      $result[] = phutil_tag(
        'iframe',
        array('src' => "/xhprof/profile/$run/?frame=true"));
    } else {
      $result[] = phutil_tag(
        'div',
        array('class' => 'dark-console-no-content'),
        pht(
          'Profiling was not enabled for this page. Use the button above '.
          'to enable it.'));
    }

    return phutil_implode_html("\n", $result);
  }


  public function willShutdown() {
    $this->profileFilePHID = DarkConsoleXHProfPluginAPI::getProfileFilePHID();
  }

}

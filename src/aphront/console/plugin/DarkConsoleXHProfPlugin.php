<?php

/**
 * @group console
 */
final class DarkConsoleXHProfPlugin extends DarkConsolePlugin {

  protected $xhprofID;

  public function getName() {
    return 'XHProf';
  }

  public function getColor() {
    $data = $this->getData();
    if ($data['xhprofID']) {
      return '#ff00ff';
    }
    return null;
  }

  public function getDescription() {
    return 'Provides detailed PHP profiling information through XHProf.';
  }

  public function generateData() {
    return array(
      'xhprofID' => $this->xhprofID,
      'profileURI' => (string)$this
        ->getRequestURI()
        ->alter('__profile__', 'page'),
    );
  }

  public function getXHProfRunID() {
    return $this->xhprofID;
  }

  public function renderPanel() {
    $data = $this->getData();

    $run = $data['xhprofID'];
    $profile_uri = $data['profileURI'];

    if (!DarkConsoleXHProfPluginAPI::isProfilerAvailable()) {
      $href = PhabricatorEnv::getDoclink('article/Installation_Guide.html');
      $install_guide = phutil_tag(
        'a',
        array(
          'href' => $href,
          'class' => 'bright-link',
        ),
        'Installation Guide');
      return hsprintf(
        '<div class="dark-console-no-content">'.
          'The "xhprof" PHP extension is not available. Install xhprof '.
          'to enable the XHProf console plugin. You can find instructions in '.
          'the %s.'.
        '</div>',
        $install_guide);
    }

    $result = array();

    $header = hsprintf(
      '<div class="dark-console-panel-header">'.
        '%s'.
        '<h1>XHProf Profiler</h1>'.
      '</div>',
      phutil_tag(
        'a',
        array(
          'href'  => $profile_uri,
          'class' => $run
            ? 'disabled button'
            : 'green button',
        ),
        'Profile Page'));
    $result[] = $header;

    if ($run) {
      $result[] = hsprintf(
        '<a href="/xhprof/profile/%s/" '.
          'class="bright-link" '.
          'style="float: right; margin: 1em 2em 0 0;'.
            'font-weight: bold;" '.
          'target="_blank">Profile Permalink</a>'.
        '<iframe src="/xhprof/profile/%s/?frame=true"></iframe>',
        $run,
        $run);
    } else {
      $result[] = hsprintf(
        '<div class="dark-console-no-content">'.
          'Profiling was not enabled for this page. Use the button above '.
          'to enable it.'.
        '</div>');
    }

    return phutil_implode_html("\n", $result);
  }


  public function willShutdown() {
    if (DarkConsoleXHProfPluginAPI::isProfilerStarted()) {
      $this->xhprofID = DarkConsoleXHProfPluginAPI::stopProfiler();
    }
  }

}

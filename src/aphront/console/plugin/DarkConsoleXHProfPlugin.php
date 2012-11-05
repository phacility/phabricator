<?php

/**
 * @group console
 */
final class DarkConsoleXHProfPlugin extends DarkConsolePlugin {

  protected $xhprofID;

  public function getName() {
    $run = $this->getData();

    if ($run) {
      return '<span style="color: #ff00ff;">&bull;</span> XHProf';
    }

    return 'XHProf';
  }

  public function getDescription() {
    return 'Provides detailed PHP profiling information through XHProf.';
  }

  public function generateData() {
    return $this->xhprofID;
  }

  public function getXHProfRunID() {
    return $this->xhprofID;
  }

  public function render() {
    if (!DarkConsoleXHProfPluginAPI::isProfilerAvailable()) {
      $href = PhabricatorEnv::getDoclink('article/Installation_Guide.html');
      $install_guide = phutil_render_tag(
        'a',
        array(
          'href' => $href,
          'class' => 'bright-link',
        ),
        'Installation Guide');
      return
        '<div class="dark-console-no-content">'.
          'The "xhprof" PHP extension is not available. Install xhprof '.
          'to enable the XHProf console plugin. You can find instructions in '.
          'the '.$install_guide.'.'.
        '</div>';
    }

    $result = array();

    $run = $this->getXHProfRunID();

    $header =
      '<div class="dark-console-panel-header">'.
        phutil_render_tag(
          'a',
          array(
            'href'  => $this->getRequestURI()->alter('__profile__', 'page'),
            'class' => $run
              ? 'disabled button'
              : 'green button',
          ),
          'Profile Page').
        '<h1>XHProf Profiler</h1>'.
      '</div>';
    $result[] = $header;

    if ($run) {
      $result[] =
        '<a href="/xhprof/profile/'.$run.'/" '.
          'class="bright-link" '.
          'style="float: right; margin: 1em 2em 0 0;'.
            'font-weight: bold;" '.
          'target="_blank">Profile Permalink</a>'.
        '<iframe src="/xhprof/profile/'.$run.'/?frame=true"></iframe>';
    } else {
      $result[] =
        '<div class="dark-console-no-content">'.
          'Profiling was not enabled for this page. Use the button above '.
          'to enable it.'.
        '</div>';
    }

    return implode("\n", $result);
  }


  public function willShutdown() {
    if (DarkConsoleXHProfPluginAPI::isProfilerRequested() &&
        (DarkConsoleXHProfPluginAPI::isProfilerRequested() !== 'all')) {
      $this->xhprofID = DarkConsoleXHProfPluginAPI::stopProfiler();
    }
  }

}

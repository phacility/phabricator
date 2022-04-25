<?php

final class PhabricatorDeveloperConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Developer / Debugging');
  }

  public function getDescription() {
    return pht('Options for platform developers, including debugging.');
  }

  public function getIcon() {
    return 'fa-bug';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('darkconsole.enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Enable DarkConsole'),
            pht('Disable DarkConsole'),
          ))
        ->setSummary(pht('Enable the debugging console.'))
        ->setDescription(
          pht(
            "DarkConsole is a development and profiling tool built into ".
            "the web interface. You should leave it disabled unless ".
            "you are developing or debugging %s.\n\n".
            "Once you activate DarkConsole for the install, **you need to ".
            "enable it for your account before it will actually appear on ".
            "pages.** You can do this in Settings > Developer Settings.\n\n".
            "DarkConsole exposes potentially sensitive data (like queries, ".
            "stack traces, and configuration) so you generally should not ".
            "turn it on in production.",
            PlatformSymbols::getPlatformServerName())),
      $this->newOption('darkconsole.always-on', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Always Activate DarkConsole'),
            pht('Require DarkConsole Activation'),
          ))
        ->setSummary(pht('Activate DarkConsole on every page.'))
        ->setDescription(
          pht(
            "This option allows you to enable DarkConsole on every page, ".
            "even for logged-out users. This is only really useful if you ".
            "need to debug something on a logged-out page. You should not ".
            "enable this option in production.\n\n".
            "You must enable DarkConsole by setting '%s' ".
            "before this option will have any effect.",
            'darkconsole.enabled')),
      $this->newOption('debug.time-limit', 'int', null)
        ->setSummary(
          pht(
            'Limit page execution time to debug hangs.'))
        ->setDescription(
          pht(
            "This option can help debug pages which are taking a very ".
            "long time (more than 30 seconds) to render.\n\n".
            "If a page is slow to render (but taking less than 30 seconds), ".
            "the best tools to use to figure out why it is slow are usually ".
            "the DarkConsole service call profiler and XHProf.\n\n".
            "However, if a request takes a very long time to return, some ".
            "components (like Apache, nginx, or PHP itself) may abort the ".
            "request before it finishes. This can prevent you from using ".
            "profiling tools to understand page performance in detail.\n\n".
            "In these cases, you can use this option to force the page to ".
            "abort after a smaller number of seconds (for example, 10), and ".
            "dump a useful stack trace. This can provide useful information ".
            "about why a page is hanging.\n\n".
            "To use this option, set it to a small number (like 10), and ".
            "reload a hanging page. The page should exit after 10 seconds ".
            "and give you a stack trace.\n\n".
            "You should turn this option off (set it to 0) when you are ".
            "done with it. Leaving it on creates a small amount of overhead ".
            "for all requests, even if they do not hit the time limit.")),
      $this->newOption('debug.stop-on-redirect', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Stop Before HTTP Redirect'),
            pht('Use Normal HTTP Redirects'),
          ))
        ->setSummary(
          pht(
            'Confirm before redirecting so DarkConsole can be examined.'))
        ->setDescription(
          pht(
            'Normally, this software issues HTTP redirects after a successful '.
            'POST. This can make it difficult to debug things which happen '.
            'while processing the POST, because service and profiling '.
            'information are lost. By setting this configuration option, '.
            'an interstitial page will be shown instead of automatically '.
            'redirecting, allowing you to examine service and profiling '.
            'information. It also makes the UX awful, so you should only '.
            'enable it when debugging.')),
      $this->newOption('debug.profile-rate', 'int', 0)
        ->addExample(0,     pht('No profiling'))
        ->addExample(1,     pht('Profile every request (slow)'))
        ->addExample(1000,  pht('Profile 0.1%% of all requests'))
        ->setSummary(pht('Automatically profile some percentage of pages.'))
        ->setDescription(
          pht(
            "Normally, pages are profiled only when explicitly ".
            "requested via DarkConsole. However, it may be useful to profile ".
            "some pages automatically.\n\n".
            "Set this option to a positive integer N to profile 1 / N pages ".
            "automatically. For example, setting it to 1 will profile every ".
            "page, while setting it to 1000 will profile 1 page per 1000 ".
            "requests (i.e., 0.1%% of requests).\n\n".
            "Since profiling is slow and generates a lot of data, you should ".
            "set this to 0 in production (to disable it) or to a large number ".
            "(to collect a few samples, if you're interested in having some ".
            "data to look at eventually). In development, it may be useful to ".
            "set it to 1 in order to debug performance problems.\n\n".
            "NOTE: You must install XHProf for profiling to work.")),
      $this->newOption('debug.sample-rate', 'int', 1000)
        ->setLocked(true)
        ->addExample(0, pht('No performance sampling.'))
        ->addExample(1, pht('Sample every request (slow).'))
        ->addExample(1000, pht('Sample 0.1%% of requests.'))
        ->setSummary(pht('Automatically sample some fraction of requests.'))
        ->setDescription(
          pht(
            "The Multimeter application collects performance samples. You ".
            "can use this data to help you understand what the software is ".
            "spending time and resources doing, and to identify problematic ".
            "access patterns.".
            "\n\n".
            "This option controls how frequently sampling activates. Set it ".
            "to some positive integer N to sample every 1 / N pages.".
            "\n\n".
            "For most installs, the default value (1 sample per 1000 pages) ".
            "should collect enough data to be useful without requiring much ".
            "storage or meaningfully impacting performance. If you're ".
            "investigating performance issues, you can adjust the rate ".
            "in order to collect more data.")),
      $this->newOption('phabricator.developer-mode', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Enable developer mode'),
            pht('Disable developer mode'),
          ))
        ->setSummary(pht('Enable verbose error reporting and disk reads.'))
        ->setDescription(
          pht(
            'This option enables verbose error reporting (stack traces, '.
            'error callouts) and forces disk reads of static assets on '.
            'every reload.')),
    );
  }
}

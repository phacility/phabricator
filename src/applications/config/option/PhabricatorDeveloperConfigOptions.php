<?php

final class PhabricatorDeveloperConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Developer / Debugging");
  }

  public function getDescription() {
    return pht("Options for Phabricator developers, including debugging.");
  }

  public function getOptions() {
    return array(
      $this->newOption('darkconsole.enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable DarkConsole"),
            pht("Disable DarkConsole"),
          ))
        ->setSummary(pht("Enable Phabricator's debugging console."))
        ->setDescription(
          pht(
            "DarkConsole is a development and profiling tool built into ".
            "Phabricator's web interface. You should leave it disabled unless ".
            "you are developing or debugging Phabricator.\n\n".
            "Set this option to enable DarkConsole, which will put a link ".
            "in the page footer to actually activate it. Once activated, ".
            "it will appear at the top of every page and can be toggled ".
            "by pressing the '`' key.\n\n".
            "DarkConsole exposes potentially sensitive data (like queries, ".
            "stack traces, and configuration) so you generally should not ".
            "turn it on in production.")),
      $this->newOption('darkconsole.always-on', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Always Activate DarkConsole"),
            pht("Require DarkConsole Activation"),
          ))
        ->setSummary(pht("Activate DarkConsole on every page."))
        ->setDescription(
          pht(
            "This option allows you to enable DarkConsole on every page, ".
            "even for logged-out users. This is only really useful if you ".
            "need to debug something on a logged-out page. You should not ".
            "enable this option in production.\n\n".
            "You must enable DarkConsole by setting {{darkconsole.enabled}} ".
            "before this option will have any effect.")),
      $this->newOption('debug.stop-on-redirect', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Stop Before HTTP Redirect"),
            pht("Use Normal HTTP Redirects"),
          ))
        ->setSummary(
          pht(
            "Confirm before redirecting so DarkConsole can be examined."))
        ->setDescription(
          pht(
            "Normally, Phabricator issues HTTP redirects after a successful ".
            "POST. This can make it difficult to debug things which happen ".
            "while processing the POST, because service and profiling ".
            "information are lost. By setting this configuration option, ".
            "Phabricator will show a page instead of automatically ".
            "redirecting, allowing you to examine service and profiling ".
            "information. It also makes the UX awful, so you should only ".
            "enable it when debugging.")),
      $this->newOption('debug.profile-rate', 'int', 0)
        ->addExample(0,     pht('No profiling'))
        ->addExample(1,     pht('Profile every request (slow)'))
        ->addExample(1000,  pht('Profile 0.1%% of all requests'))
        ->setSummary(pht("Automatically profile some percentage of pages."))
        ->setDescription(
          pht(
            "Normally, Phabricator profiles pages only when explicitly ".
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
      $this->newOption('phabricator.developer-mode', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Enable developer mode'),
            pht('Disable developer mode'),
          ))
          ->setSummary(pht("Enable verbose error reporting and disk reads."))
          ->setDescription(
            pht(
              "This option enables verbose error reporting (stack traces, ".
              "error callouts) and forces disk reads of static assets on ".
              "every reload.")),
      $this->newOption('celerity.minify', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Minify static resources.'),
            pht("Don't minify static resources."),
          ))
        ->setSummary(pht("Minify static Celerity resources."))
        ->setDescription(
          pht(
            "Minify static resources by removing whitespace and comments. You ".
            "should enable this in production, but disable it in ".
            "development.")),
      $this->newOption('cache.enable-deflate', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Enable deflate compression"),
            pht("Disable deflate compression"),
          ))
        ->setSummary(
          pht("Toggle gzdeflate()-based compression for some caches."))
        ->setDescription(
          pht(
            "Set this to false to disable the use of gzdeflate()-based ".
            "compression in some caches. This may give you less performant ".
            "(but more debuggable) caching.")),
    );
  }
}

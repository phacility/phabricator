<?php

final class PhabricatorDisqusConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Integration with Disqus');
  }

  public function getDescription() {
    return pht('Disqus authentication and integration options.');
  }

  public function getFontIcon() {
    return 'fa-comment';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('disqus.shortname', 'string', null)
        ->setSummary(pht('Shortname for Disqus comment widget.'))
        ->setDescription(
          pht(
            "Website shortname to use for Disqus comment widget in Phame. ".
            "For more information, see:\n\n".
            "[[http://docs.disqus.com/help/4/ | Disqus Quick Start Guide]]\n".
            "[[http://docs.disqus.com/help/68/ | Information on Shortnames]]")),
    );
  }

}

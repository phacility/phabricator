<?php

final class PhabricatorBugzillaLinkRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 360.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '/bug\s*#?\s*(\d+)(\s*comment\s*\#?\s*(\d+))?/i',
      array($this, 'markupBugzillaLink'),
      $text
    );
  }

  private function markupBugzillaLink(array $matches) {
    $text = $matches[0];
    $bug = $matches[1];
    $comment = null;
    if (count($matches) == 4) {
      $comment = $matches[3];
    }

    $uri = id(new PhutilURI(PhabricatorEnv::getEnvConfig('bugzilla.url')))
        ->setPath('/show_bug.cgi')
        ->setQueryParam('id', $bug);
    if ($comment) {
      $uri->setFragment('c' . $comment);
    }

    $link = $this->newTag('a', array('href' => $uri), $text);

    return $this->getEngine()->storeText($link);
  }

}

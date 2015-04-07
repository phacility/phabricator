<?php

final class PhabricatorDivinerApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/diviner/';
  }

  public function getFontIcon() {
    return 'fa-sun-o';
  }

  public function getName() {
    return pht('Diviner');
  }

  public function getShortDescription() {
    return pht('Documentation');
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Diviner User Guide'),
        'href' => PhabricatorEnv::getDoclink('Diviner User Guide'),
      ),
    );
  }

  public function getTitleGlyph() {
    return "\xE2\x97\x89";
  }

  public function getRoutes() {
    return array(
      '/diviner/' => array(
        '' => 'DivinerMainController',
        'query/((?<key>[^/]+)/)?' => 'DivinerAtomListController',
        'find/' => 'DivinerFindController',
      ),
      '/book/(?P<book>[^/]+)/' => 'DivinerBookController',
      '/book/'.
        '(?P<book>[^/]+)/'.
        '(?P<type>[^/]+)/'.
        '(?:(?P<context>[^/]+)/)?'.
        '(?P<name>[^/]+)/'.
        '(?:(?P<index>\d+)/)?' => 'DivinerAtomController',
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return array(
      new DivinerSymbolRemarkupRule(),
    );
  }

}

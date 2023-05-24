<?php

final class PhabricatorRemarkupCowsayBlockInterpreter
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'cowsay';
  }

  public function markupContent($content, array $argv) {
    $action = idx($argv, 'think') ? 'think' : 'say';
    $eyes = idx($argv, 'eyes', 'oo');
    $tongue = idx($argv, 'tongue', '  ');

    $map = self::getCowMap();

    $cow = idx($argv, 'cow');
    $cow = ($cow === null ? '' : $cow);
    $cow = phutil_utf8_strtolower($cow);
    if (empty($map[$cow])) {
      $cow = 'default';
    }

    $result = id(new PhutilCowsay())
      ->setTemplate($map[$cow])
      ->setAction($action)
      ->setEyes($eyes)
      ->setTongue($tongue)
      ->setText($content)
      ->renderCow();

    $engine = $this->getEngine();

    if ($engine->isTextMode()) {
      return $result;
    }

    if ($engine->isHTMLMailMode()) {
      return phutil_tag('pre', array(), $result);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'PhabricatorMonospaced remarkup-cowsay',
      ),
      $result);
  }

  private static function getCowMap() {
    $root = dirname(phutil_get_library_root('phabricator'));

    $directories = array(
      $root.'/externals/cowsay/cows/',
      $root.'/resources/cows/builtin/',
      $root.'/resources/cows/custom/',
    );

    $map = array();
    foreach ($directories as $directory) {
      foreach (Filesystem::listDirectory($directory, false) as $cow_file) {
        $matches = null;
        if (!preg_match('/^(.*)\.cow\z/', $cow_file, $matches)) {
          continue;
        }
        $cow_name = $matches[1];
        $cow_name = phutil_utf8_strtolower($cow_name);
        $map[$cow_name] = Filesystem::readFile($directory.$cow_file);
      }
    }

    return $map;
  }

}

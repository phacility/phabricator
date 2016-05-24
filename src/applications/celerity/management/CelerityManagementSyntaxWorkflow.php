<?php

final class CelerityManagementSyntaxWorkflow
  extends CelerityManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('syntax')
      ->setExamples('**syntax** [options]')
      ->setSynopsis(pht('Rebuild syntax highlighting CSS.'))
      ->setArguments(
        array());
  }

  public function execute(PhutilArgumentParser $args) {
    $styles = PhabricatorSyntaxStyle::getAllStyles();

    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/webroot/rsrc/css/syntax/';

    foreach ($styles as $key => $style) {
      $content = $this->generateCSS($style);
      $path = $root.'/syntax-'.$key.'.css';
      Filesystem::writeFile($path, $content);

      echo tsprintf(
        "%s\n",
        pht(
          'Rebuilt "%s" syntax CSS.',
          basename($path)));
    }

    return 0;
  }

  private function generateCSS(PhabricatorSyntaxStyle $style) {
    $key = $style->getSyntaxStyleKey();
    $provides = "syntax-{$key}-css";
    $generated = 'generated';

    $header =
      "/**\n".
      " * @provides {$provides}\n".
      " * @{$generated}\n".
      " */\n\n";

    $groups = array();
    $map = $style->getStyleMap();
    ksort($map);
    foreach ($map as $key => $value) {
      $groups[$value][] = $key;
    }

    $rules = array();
    foreach ($groups as $body => $classes) {
      $parts = array();
      foreach ($classes as $class) {
        $parts[] = ".remarkup-code .{$class}";
      }
      $rules[] = implode(",\n", $parts)." {\n  {$body}\n}";
    }
    $rules = implode("\n\n", $rules);

    return $header.$rules."\n";
  }

}

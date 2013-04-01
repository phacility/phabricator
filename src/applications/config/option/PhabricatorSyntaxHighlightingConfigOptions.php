<?php

final class PhabricatorSyntaxHighlightingConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Syntax Highlighting");
  }

  public function getDescription() {
    return pht("Options relating to syntax highlighting source code.");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'syntax-highlighter.engine',
        'class',
        'PhutilDefaultSyntaxHighlighterEngine')
        ->setBaseClass('PhutilSyntaxHighlighterEngine')
        ->setSummary(pht("Default non-pygments syntax highlighter engine."))
        ->setDescription(
          pht(
            "Phabricator can highlight PHP by default and use Pygments for ".
            "other languages if enabled. You can provide a custom ".
            "highlighter engine by extending class ".
            "PhutilSyntaxHighlighterEngine.")),
      $this->newOption('pygments.enabled', 'bool', false)
        ->setSummary(
          pht("Should Phabricator shell out to Pygments to highlight code?"))
        ->setDescription(
          pht(
            "If you want syntax highlighting for other languages than PHP ".
            "then you can install the python package 'Pygments', make sure ".
            "the 'pygmentize' script is  available in the \$PATH of the ".
            "webserver, and then enable this.")),
      $this->newOption(
        'pygments.dropdown-choices',
        'wild',
        array(
          'apacheconf' => 'Apache Configuration',
          'bash' => 'Bash Scripting',
          'brainfuck' => 'Brainf*ck',
          'c' => 'C',
          'cpp' => 'C++',
          'css' => 'CSS',
          'd' => 'D',
          'diff' => 'Diff',
          'django' => 'Django Templating',
          'erb' => 'Embedded Ruby/ERB',
          'erlang' => 'Erlang',
          'haskell' => 'Haskell',
          'html' => 'HTML',
          'java' => 'Java',
          'js' => 'Javascript',
          'mysql' => 'MySQL',
          'objc' => 'Objective-C',
          'perl' => 'Perl',
          'php' => 'PHP',
          'rest' => 'reStructuredText',
          'text' => 'Plain Text',
          'python' => 'Python',
          'rainbow' => 'Rainbow',
          'remarkup' => 'Remarkup',
          'ruby' => 'Ruby',
          'xml' => 'XML',
        ))
        ->setSummary(
          pht("Set the language list which appears in dropdowns."))
        ->setDescription(
          pht(
            "In places that we display a dropdown to syntax-highlight code, ".
            "this is where that list is defined.")),
      $this->newOption(
        'syntax.filemap',
        'wild',
        array(
          '@\.arcconfig$@' => 'js',
          '@\.divinerconfig$@' => 'js',
        ))
        ->setSummary(
          pht("Override what language files (based on filename) highlight as."))
        ->setDescription(
          pht(
            "This is an override list of regular expressions which allows ".
            "you to choose what language files are highlighted as. If your ".
            "projects have certain rules about filenames or use unusual or ".
            "ambiguous language extensions, you can create a mapping here. ".
            "This is an ordered dictionary of regular expressions which will ".
            "be tested against the filename. They should map to either an ".
            "explicit language as a string value, or a numeric index into ".
            "the captured groups as an integer."))
      ->addExample('{"@\\.xyz$@": "php"}', pht('Highlight *.xyz as PHP.'))
      ->addExample(
        '{"@/httpd\\.conf@": "apacheconf"}',
        pht('Highlight httpd.conf as "apacheconf".'))
      ->addExample(
        '{"@\\.([^.]+)\\.bak$@": 1}',
        pht(
          "Treat all '*.x.bak' file as '.x'. NOTE: We map to capturing group ".
          "1 by specifying the mapping as '1'")),
      $this->newOption(
        'style.monospace',
        'string',
        '10px "Menlo", "Consolas", "Monaco", monospace')
        ->setLocked(true)
        ->setSummary(
          pht("Default monospace font."))
        ->setDescription(
          pht(
            "Set the default monospaced font style for users who haven't set ".
            "a custom style.")),
      $this->newOption(
        'style.monospace.windows',
        'string',
        '11px "Menlo", "Consolas", "Monaco", monospace')
        ->setLocked(true)
        ->setSummary(
          pht("Default monospace font for clients on Windows."))
        ->setDescription(
          pht(
            "Set the default monospaced font style for users who haven't set ".
            "a custom style and are using Windows.")),
    );
  }

}

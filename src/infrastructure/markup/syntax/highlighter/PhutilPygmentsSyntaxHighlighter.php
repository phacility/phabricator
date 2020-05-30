<?php

final class PhutilPygmentsSyntaxHighlighter extends Phobject {

  private $config = array();

  public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  public function getHighlightFuture($source) {
    $language = idx($this->config, 'language');

    if (preg_match('/\r(?!\n)/', $source)) {
      // TODO: Pygments converts "\r" newlines into "\n" newlines, so we can't
      // use it on files with "\r" newlines. If we have "\r" not followed by
      // "\n" in the file, skip highlighting.
      $language = null;
    }

    if ($language) {
      $language = $this->getPygmentsLexerNameFromLanguageName($language);

      // See T13224. Under Ubuntu, avoid leaving an intermedite "dash" shell
      // process so we hit "pygmentize" directly if we have to SIGKILL this
      // because it explodes.

      $future = new ExecFuture(
        'exec pygmentize -O encoding=utf-8 -O stripnl=False -f html -l %s',
        $language);

      $scrub = false;
      if ($language == 'php' && strpos($source, '<?') === false) {
        $source = "<?php\n".$source;
        $scrub = true;
      }

      // See T13224. In some cases, "pygmentize" has explosive runtime on small
      // inputs. Put a hard cap on how long it is allowed to run for to limit
      // the amount of damage it can do.
      $future->setTimeout(15);

      $future->write($source);

      return new PhutilDefaultSyntaxHighlighterEnginePygmentsFuture(
        $future,
        $source,
        $scrub);
    }

    return id(new PhutilDefaultSyntaxHighlighter())
      ->getHighlightFuture($source);
  }

  private function getPygmentsLexerNameFromLanguageName($language) {
    static $map = array(
      'adb' => 'ada',
      'ads' => 'ada',
      'ahkl' => 'ahk',
      'as' => 'as3',
      'asax' => 'aspx-vb',
      'ascx' => 'aspx-vb',
      'ashx' => 'aspx-vb',
      'ASM' => 'nasm',
      'asm' => 'nasm',
      'asmx' => 'aspx-vb',
      'aspx' => 'aspx-vb',
      'autodelegate' => 'myghty',
      'autohandler' => 'mason',
      'aux' => 'tex',
      'axd' => 'aspx-vb',
      'b' => 'brainfuck',
      'bas' => 'vb.net',
      'bf' => 'brainfuck',
      'bmx' => 'blitzmax',
      'c++' => 'cpp',
      'c++-objdump' => 'cpp-objdump',
      'cc' => 'cpp',
      'cfc' => 'cfm',
      'cfg' => 'ini',
      'cfml' => 'cfm',
      'cl' => 'common-lisp',
      'clj' => 'clojure',
      'cmd' => 'bat',
      'coffee' => 'coffee-script',
      'cs' => 'csharp',
      'csh' => 'tcsh',
      'cw' => 'redcode',
      'cxx' => 'cpp',
      'cxx-objdump' => 'cpp-objdump',
      'darcspatch' => 'dpatch',
      'def' => 'modula2',
      'dhandler' => 'mason',
      'di' => 'd',
      'duby' => 'rb',
      'dyl' => 'dylan',
      'ebuild' => 'bash',
      'eclass' => 'bash',
      'el' => 'common-lisp',
      'eps' => 'postscript',
      'erl' => 'erlang',
      'erl-sh' => 'erl',
      'f' => 'fortran',
      'f90' => 'fortran',
      'feature' => 'Cucumber',
      'fhtml' => 'velocity',
      'flx' => 'felix',
      'flxh' => 'felix',
      'frag' => 'glsl',
      'g' => 'antlr-ruby',
      'G' => 'antlr-ruby',
      'gdc' => 'gooddata-cl',
      'gemspec' => 'rb',
      'geo' => 'glsl',
      'GNUmakefile' => 'make',
      'h' => 'c',
      'h++' => 'cpp',
      'hh' => 'cpp',
      'hpp' => 'cpp',
      'hql' => 'sql',
      'hrl' => 'erlang',
      'hs' => 'haskell',
      'htaccess' => 'apacheconf',
      'htm' => 'html',
      'html' => 'html+evoque',
      'hxx' => 'cpp',
      'hy' => 'hybris',
      'hyb' => 'hybris',
      'ik' => 'ioke',
      'inc' => 'pov',
      'j' => 'objective-j',
      'jbst' => 'duel',
      'kid' => 'genshi',
      'ksh' => 'bash',
      'less' => 'css',
      'lgt' => 'logtalk',
      'lisp' => 'common-lisp',
      'll' => 'llvm',
      'm' => 'objective-c',
      'mak' => 'make',
      'Makefile' => 'make',
      'makefile' => 'make',
      'man' => 'groff',
      'mao' => 'mako',
      'mc' => 'mason',
      'md' => 'minid',
      'mhtml' => 'mason',
      'mi' => 'mason',
      'ml' => 'ocaml',
      'mli' => 'ocaml',
      'mll' => 'ocaml',
      'mly' => 'ocaml',
      'mm' => 'objective-c',
      'mo' => 'modelica',
      'mod' => 'modula2',
      'moo' => 'moocode',
      'mu' => 'mupad',
      'myt' => 'myghty',
      'ns2' => 'newspeak',
      'pas' => 'delphi',
      'patch' => 'diff',
      'phtml' => 'html+php',
      'pl' => 'prolog',
      'plot' => 'gnuplot',
      'plt' => 'gnuplot',
      'pm' => 'perl',
      'po' => 'pot',
      'pp' => 'puppet',
      'pro' => 'prolog',
      'proto' => 'protobuf',
      'ps' => 'postscript',
      'pxd' => 'cython',
      'pxi' => 'cython',
      'py' => 'python',
      'pyw' => 'python',
      'pyx' => 'cython',
      'R' => 'splus',
      'r' => 'rebol',
      'r3' => 'rebol',
      'rake' => 'rb',
      'Rakefile' => 'rb',
      'rbw' => 'rb',
      'rbx' => 'rb',
      'rest' => 'rst',
      'rl' => 'ragel-em',
      'robot' => 'robotframework',
      'Rout' => 'rconsole',
      'rss' => 'xml',
      's' => 'gas',
      'S' => 'splus',
      'sc' => 'python',
      'scm' => 'scheme',
      'SConscript' => 'python',
      'SConstruct' => 'python',
      'scss' => 'css',
      'sh' => 'bash',
      'sh-session' => 'console',
      'spt' => 'cheetah',
      'sqlite3-console' => 'sqlite3',
      'st' => 'smalltalk',
      'sv' => 'v',
      'tac' => 'python',
      'tmpl' => 'cheetah',
      'toc' => 'tex',
      'tpl' => 'smarty',
      'txt' => 'text',
      'vapi' => 'vala',
      'vb' => 'vb.net',
      'vert' => 'glsl',
      'vhd' => 'vhdl',
      'vimrc' => 'vim',
      'vm' => 'velocity',
      'weechatlog' => 'irc',
      'wlua' => 'lua',
      'wsdl' => 'xml',
      'xhtml' => 'html',
      'xml' => 'xml+evoque',
      'xqy' => 'xquery',
      'xsd' => 'xml',
      'xsl' => 'xslt',
      'xslt' => 'xml',
      'yml' => 'yaml',
    );

    return idx($map, $language, $language);
  }

}

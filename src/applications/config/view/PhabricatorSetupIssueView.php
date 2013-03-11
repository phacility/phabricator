<?php

final class PhabricatorSetupIssueView extends AphrontView {

  private $issue;

  public function setIssue(PhabricatorSetupIssue $issue) {
    $this->issue = $issue;
    return $this;
  }

  public function getIssue() {
    return $this->issue;
  }

  public function render() {
    $issue = $this->getIssue();

    $description = array();
    $description[] = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-instructions',
      ),
      phutil_escape_html_newlines($issue->getMessage()));

    $configs = $issue->getPHPConfig();
    if ($configs) {
      $description[] = $this->renderPHPConfig($configs);
    }

    $configs = $issue->getPhabricatorConfig();
    if ($configs) {
      $description[] = $this->renderPhabricatorConfig($configs);
    }

    $commands = $issue->getCommands();
    if ($commands) {
      $run_these = pht("Run these %d command(s):", count($commands));
      $description[] = phutil_tag(
        'div',
        array(
          'class' => 'setup-issue-config',
        ),
        array(
          phutil_tag('p', array(), $run_these),
          phutil_tag('pre', array(), phutil_implode_html("\n", $commands)),
        ));
    }

    $extensions = $issue->getPHPExtensions();
    if ($extensions) {
      $install_these = pht(
        "Install these %d PHP extension(s):", count($extensions));

      $install_info = pht(
        "You can usually install a PHP extension using %s or %s. Common ".
        "package names are %s or %s. Try commands like these:",
        phutil_tag('tt', array(), 'apt-get'),
        phutil_tag('tt', array(), 'yum'),
        hsprintf('<tt>php-<em>%s</em></tt>', pht('extname')),
        hsprintf('<tt>php5-<em>%s</em></tt>', pht('extname')));

      // TODO: We should do a better job of detecting how to install extensions
      // on the current system.
      $install_commands = hsprintf(
        "\$ sudo apt-get install php5-<em>extname</em>  ".
        "# Debian / Ubuntu\n".
        "\$ sudo yum install php-<em>extname</em>       ".
        "# Red Hat / Derivatives");

      $fallback_info = pht(
        "If those commands don't work, try Google. The process of installing ".
        "PHP extensions is not specific to Phabricator, and any instructions ".
        "you can find for installing them on your system should work. On Mac ".
        "OS X, you might want to try Homebrew.");

      $restart_info = pht(
        "After installing new PHP extensions, <strong>restart your webserver ".
        "for the changes to take effect</strong>.",
        hsprintf(''));

      $description[] = phutil_tag(
        'div',
        array(
          'class' => 'setup-issue-config',
        ),
        array(
          phutil_tag('p', array(), $install_these),
          phutil_tag('pre', array(), implode("\n", $extensions)),
          phutil_tag('p', array(), $install_info),
          phutil_tag('pre', array(), $install_commands),
          phutil_tag('p', array(), $fallback_info),
          phutil_tag('p', array(), $restart_info),
        ));

    }

    $next = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-next',
      ),
      pht('To continue, resolve this problem and reload the page.'));

    $name = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-name',
      ),
      $issue->getName());

    return phutil_tag(
      'div',
      array(
        'class' => 'setup-issue',
      ),
      array(
        $name,
        $description,
        $next,
      ));
  }

  private function renderPhabricatorConfig(array $configs) {
    $issue = $this->getIssue();

    $table_info = phutil_tag(
      'p',
      array(),
      pht(
        "The current Phabricator configuration has these %d value(s):",
        count($configs)));

    $dict = array();
    foreach ($configs as $key) {
      $dict[$key] = PhabricatorEnv::getUnrepairedEnvConfig($key);
    }
    $table = $this->renderValueTable($dict);

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();

    if ($this->getIssue()->getIsFatal()) {
      $update_info = phutil_tag(
        'p',
        array(),
        pht(
          "To update these %d value(s), run these command(s) from the command ".
          "line:",
          count($configs)));

      $update = array();
      foreach ($configs as $key) {
        $update[] = hsprintf(
          '<tt>phabricator/ $</tt> ./bin/config set %s <em>value</em>',
          $key);
      }
      $update = phutil_tag('pre', array(), phutil_implode_html("\n", $update));
    } else {
      $update = array();
      foreach ($configs as $config) {
        if (!idx($options, $config) || $options[$config]->getLocked()) {
          continue;
        }
        $link = phutil_tag(
          'a',
          array(
            'href' => '/config/edit/'.$config.'/?issue='.$issue->getIssueKey(),
          ),
          pht('Edit %s', $config));
        $update[] = phutil_tag('li', array(), $link);
      }
      if ($update) {
        $update = phutil_tag('ul', array(), $update);
        $update_info = phutil_tag(
          'p',
          array(),
          pht("You can update these %d value(s) here:", count($configs)));
      } else {
        $update = null;
        $update_info = null;
      }
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-config',
      ),
      array(
        $table_info,
        $table,
        $update_info,
        $update,
      ));
  }

  private function renderPHPConfig(array $configs) {
    $table_info = phutil_tag(
      'p',
      array(),
      pht(
        "The current PHP configuration has these %d value(s):",
        count($configs)));

    $dict = array();
    foreach ($configs as $key) {
      $dict[$key] = ini_get($key);
    }

    $table = $this->renderValueTable($dict);

    ob_start();
      phpinfo();
    $phpinfo = ob_get_clean();


    $rex = '@Loaded Configuration File\s*</td><td class="v">(.*?)</td>@i';
    $matches = null;

    $ini_loc = null;
    if (preg_match($rex, $phpinfo, $matches)) {
      $ini_loc = trim($matches[1]);
    }

    $rex = '@Additional \.ini files parsed\s*</td><td class="v">(.*?)</td>@i';

    $more_loc = array();
    if (preg_match($rex, $phpinfo, $matches)) {
      $more_loc = trim($matches[1]);
      if ($more_loc == '(none)') {
        $more_loc = array();
      } else {
        $more_loc = preg_split('/\s*,\s*/', $more_loc);
      }
    }

    $info = array();
    if (!$ini_loc) {
      $info[] = phutil_tag(
        'p',
        array(),
        pht(
          "To update these %d value(s), edit your PHP configuration file.",
          count($configs)));
    } else {
      $info[] = phutil_tag(
        'p',
        array(),
        pht(
          "To update these %d value(s), edit your PHP configuration file, ".
          "located here:",
          count($configs)));
      $info[] = phutil_tag(
        'pre',
        array(),
        $ini_loc);
    }

    if ($more_loc) {
      $info[] = phutil_tag(
        'p',
        array(),
        pht(
          "PHP also loaded these configuration file(s):",
          count($more_loc)));
      $info[] = phutil_tag(
        'pre',
        array(),
        implode("\n", $more_loc));
    }

    $info[] = phutil_tag(
      'p',
      array(),
      pht(
        'You can find more information about PHP configuration values in the '.
        '<a href="%s">PHP Documentation</a>.',
        'http://php.net/manual/ini.list.php',
        hsprintf('')));

    $info[] = phutil_tag(
      'p',
      array(),
      pht(
        "After editing the PHP configuration, <strong>restart your ".
        "webserver for the changes to take effect</strong>.",
        hsprintf('')));

    return phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-config',
      ),
      array(
        $table_info,
        $table,
        $info,
      ));
  }

  private function renderValueTable(array $dict) {
    $rows = array();
    foreach ($dict as $key => $value) {
      $cols = array(
        phutil_tag('th', array(), $key),
        phutil_tag('td', array(), $this->renderValueForDisplay($value)),
      );
      $rows[] = phutil_tag('tr', array(), $cols);
    }
    return phutil_tag('table', array(), $rows);
  }

  private function renderValueForDisplay($value) {
    if ($value === null) {
      return phutil_tag('em', array(), 'null');
    } else if ($value === false) {
      return phutil_tag('em', array(), 'false');
    } else if ($value === true) {
      return phutil_tag('em', array(), 'true');
    } else if ($value === '') {
      return phutil_tag('em', array(), 'empty string');
    } else {
      return PhabricatorConfigJSON::prettyPrintJSON($value);
    }
  }

}

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

  public function renderInFlight() {
    $issue = $this->getIssue();

    return id(new PhabricatorInFlightErrorView())
      ->setMessage($issue->getName())
      ->render();
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
      $description[] = $this->renderPHPConfig($configs, $issue);
    }

    $configs = $issue->getMySQLConfig();
    if ($configs) {
      $description[] = $this->renderMySQLConfig($configs);
    }

    $configs = $issue->getPhabricatorConfig();
    if ($configs) {
      $description[] = $this->renderPhabricatorConfig($configs);
    }

    $related_configs = $issue->getRelatedPhabricatorConfig();
    if ($related_configs) {
      $description[] = $this->renderPhabricatorConfig($related_configs,
        $related = true);
    }

    $commands = $issue->getCommands();
    if ($commands) {
      $run_these = pht('Run these %d command(s):', count($commands));
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
        'Install these %d PHP extension(s):', count($extensions));

      $install_info = pht(
        'You can usually install a PHP extension using %s or %s. Common '.
        'package names are %s or %s. Try commands like these:',
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
        'After installing new PHP extensions, <strong>restart Phabricator '.
        'for the changes to take effect</strong>. For help with restarting '.
        'Phabricator, see %s in the documentation.',
        $this->renderRestartLink());

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

    $related_links = $issue->getLinks();
    if ($related_links) {
      $description[] = $this->renderRelatedLinks($related_links);
    }

    $actions = array();
    if (!$issue->getIsFatal()) {
      if ($issue->getIsIgnored()) {
        $actions[] = javelin_tag(
          'a',
          array(
            'href' => '/config/unignore/'.$issue->getIssueKey().'/',
            'sigil' => 'workflow',
            'class' => 'button grey',
          ),
          pht('Unignore Setup Issue'));
      } else {
        $actions[] = javelin_tag(
          'a',
          array(
            'href' => '/config/ignore/'.$issue->getIssueKey().'/',
            'sigil' => 'workflow',
            'class' => 'button grey',
          ),
          pht('Ignore Setup Issue'));
      }

      $actions[] = javelin_tag(
        'a',
        array(
          'href' => '/config/issue/'.$issue->getIssueKey().'/',
          'class' => 'button grey',
          'style' => 'float: right',
        ),
        pht('Reload Page'));
    }

    if ($actions) {
      $actions = phutil_tag(
        'div',
        array(
          'class' => 'setup-issue-actions',
        ),
        $actions);
    }

    if ($issue->getIsIgnored()) {
      $status = phutil_tag(
        'div',
        array(
          'class' => 'setup-issue-status',
        ),
        pht(
          'This issue is currently ignored, and does not show a global '.
          'warning.'));
      $next = null;
    } else {
      $status = null;
      $next = phutil_tag(
        'div',
        array(
          'class' => 'setup-issue-next',
        ),
        pht('To continue, resolve this problem and reload the page.'));
    }

    $name = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-name',
      ),
      $issue->getName());

    $head = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-head',
      ),
      array($name, $status));

    $tail = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-tail',
      ),
      array($actions));

    $issue = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue',
      ),
      array(
        $head,
        $description,
        $tail,
      ));

    $debug_info = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-debug',
      ),
      pht('Host: %s', php_uname('n')));

    return phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-shell',
      ),
      array(
        $issue,
        $next,
        $debug_info,
      ));
  }

  private function renderPhabricatorConfig(array $configs, $related = false) {
    $issue = $this->getIssue();

    $table_info = phutil_tag(
      'p',
      array(),
      pht(
        'The current Phabricator configuration has these %d value(s):',
        count($configs)));

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    $hidden = array();
    foreach ($options as $key => $option) {
      if ($option->getHidden()) {
        $hidden[$key] = true;
      }
    }

    $table = null;
    $dict = array();
    foreach ($configs as $key) {
      if (isset($hidden[$key])) {
        $dict[$key] = null;
      } else {
        $dict[$key] = PhabricatorEnv::getUnrepairedEnvConfig($key);
      }
    }

    $table = $this->renderValueTable($dict, $hidden);

    if ($this->getIssue()->getIsFatal()) {
      $update_info = phutil_tag(
        'p',
        array(),
        pht(
          'To update these %d value(s), run these command(s) from the command '.
          'line:',
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
        if (idx($options, $config) && $options[$config]->getLocked()) {
          $name = pht('View "%s"', $config);
        } else {
          $name = pht('Edit "%s"', $config);
        }
        $link = phutil_tag(
          'a',
          array(
            'href' => '/config/edit/'.$config.'/?issue='.$issue->getIssueKey(),
          ),
          $name);
        $update[] = phutil_tag('li', array(), $link);
      }
      if ($update) {
        $update = phutil_tag('ul', array(), $update);
        if (!$related) {
          $update_info = phutil_tag(
          'p',
          array(),
          pht('You can update these %d value(s) here:', count($configs)));
        } else {
          $update_info = phutil_tag(
          'p',
          array(),
          pht('These %d configuration value(s) are related:', count($configs)));
        }
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

  private function renderPHPConfig(array $configs, $issue) {
    $table_info = phutil_tag(
      'p',
      array(),
      pht(
        'The current PHP configuration has these %d value(s):',
        count($configs)));

    $dict = array();
    foreach ($configs as $key) {
      $dict[$key] = $issue->getPHPConfigOriginalValue(
        $key,
        ini_get($key));
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
          'To update these %d value(s), edit your PHP configuration file.',
          count($configs)));
    } else {
      $info[] = phutil_tag(
        'p',
        array(),
        pht(
          'To update these %d value(s), edit your PHP configuration file, '.
          'located here:',
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
          'PHP also loaded these %s configuration file(s):',
          phutil_count($more_loc)));
      $info[] = phutil_tag(
        'pre',
        array(),
        implode("\n", $more_loc));
    }

    $show_standard = false;
    $show_opcache = false;

    foreach ($configs as $key) {
      if (preg_match('/^opcache\./', $key)) {
        $show_opcache = true;
      } else {
        $show_standard = true;
      }
    }

    if ($show_standard) {
      $info[] = phutil_tag(
        'p',
        array(),
        pht(
          'You can find more information about PHP configuration values '.
          'in the %s.',
          phutil_tag(
            'a',
            array(
              'href' => 'http://php.net/manual/ini.list.php',
              'target' => '_blank',
            ),
            pht('PHP Documentation'))));
    }

    if ($show_opcache) {
      $info[] = phutil_tag(
        'p',
        array(),
        pht(
          'You can find more information about configuring OPCache in '.
          'the %s.',
          phutil_tag(
            'a',
            array(
              'href' => 'http://php.net/manual/opcache.configuration.php',
              'target' => '_blank',
            ),
            pht('PHP OPCache Documentation'))));
    }

    $info[] = phutil_tag(
      'p',
      array(),
      pht(
        'After editing the PHP configuration, <strong>restart Phabricator for '.
        'the changes to take effect</strong>. For help with restarting '.
        'Phabricator, see %s in the documentation.',
        $this->renderRestartLink()));

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

  private function renderMySQLConfig(array $config) {
    $values = array();
    $issue = $this->getIssue();
    $ref = $issue->getDatabaseRef();
    if ($ref) {
      foreach ($config as $key) {
        $value = $ref->loadRawMySQLConfigValue($key);
        if ($value === null) {
          $value = phutil_tag(
            'em',
            array(),
            pht('(Not Supported)'));
        }
        $values[$key] = $value;
      }
    }

    $table = $this->renderValueTable($values);

    $doc_href = PhabricatorEnv::getDoclink('User Guide: Amazon RDS');
    $doc_link = phutil_tag(
      'a',
      array(
        'href' => $doc_href,
        'target' => '_blank',
      ),
      pht('User Guide: Amazon RDS'));

    $info = array();
    $info[] = phutil_tag(
      'p',
      array(),
      pht(
        'If you are using Amazon RDS, some of the instructions above may '.
        'not apply to you. See %s for discussion of Amazon RDS.',
        $doc_link));

    $table_info = phutil_tag(
      'p',
      array(),
      pht(
        'The current MySQL configuration has these %d value(s):',
        count($config)));

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

  private function renderValueTable(array $dict, array $hidden = array()) {
    $rows = array();
    foreach ($dict as $key => $value) {
      if (isset($hidden[$key])) {
        $value = phutil_tag('em', array(), 'hidden');
      } else {
        $value = $this->renderValueForDisplay($value);
      }

      $cols = array(
        phutil_tag('th', array(), $key),
        phutil_tag('td', array(), $value),
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
    } else if ($value instanceof PhutilSafeHTML) {
      return $value;
    } else {
      return PhabricatorConfigJSON::prettyPrintJSON($value);
    }
  }

  private function renderRelatedLinks(array $links) {
    $link_info = phutil_tag(
      'p',
      array(),
      pht(
        '%d related link(s):',
        count($links)));

    $link_list = array();
    foreach ($links as $link) {
      $link_tag = phutil_tag(
        'a',
        array(
          'target' => '_blank',
          'href' => $link['href'],
        ),
        $link['name']);
      $link_item = phutil_tag('li', array(), $link_tag);
      $link_list[] = $link_item;
    }
    $link_list = phutil_tag('ul', array(), $link_list);

    return phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-config',
      ),
      array(
        $link_info,
        $link_list,
      ));
  }

  private function renderRestartLink() {
    $doc_href = PhabricatorEnv::getDoclink('Restarting Phabricator');
    return phutil_tag(
      'a',
      array(
        'href' => $doc_href,
        'target' => '_blank',
      ),
      pht('Restarting Phabricator'));
  }

}

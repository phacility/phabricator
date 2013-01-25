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

    $description = phutil_render_tag(
      'div',
      array(
        'class' => 'setup-issue-instructions',
      ),
      nl2br(phutil_escape_html($issue->getMessage())));

    $configs = $issue->getPHPConfig();
    if ($configs) {
      $description .= $this->renderPHPConfig($configs);
    }

    $configs = $issue->getPhabricatorConfig();
    if ($configs) {
      $description .= $this->renderPhabricatorConfig($configs);
    }

    $commands = $issue->getCommands();
    if ($commands) {
      $run_these = pht("Run these %d command(s):", count($commands));
      $description .= phutil_render_tag(
        'div',
        array(
          'class' => 'setup-issue-config',
        ),
        phutil_render_tag('p', array(), $run_these).
        phutil_render_tag('pre', array(), implode("\n", $commands)));
    }

    $extensions = $issue->getPHPExtensions();
    if ($extensions) {
      $install_these = pht(
        "Install these %d PHP extension(s):", count($extensions));

      $install_info = pht(
        "You can usually install a PHP extension using <tt>apt-get</tt> or ".
        "<tt>yum</tt>. Common package names are ".
        "<tt>php-<em>extname</em></tt> or <tt>php5-<em>extname</em></tt>. ".
        "Try commands like these:");

      // TODO: We should do a better job of detecting how to install extensions
      // on the current system.
      $install_commands = array(
        "$ sudo apt-get install php5-<em>extname</em>  # Debian / Ubuntu",
        "$ sudo yum install php-<em>extname</em>       # Red Hat / Derivatives",
      );
      $install_commands = implode("\n", $install_commands);

      $fallback_info = pht(
        "If those commands don't work, try Google. The process of installing ".
        "PHP extensions is not specific to Phabricator, and any instructions ".
        "you can find for installing them on your system should work. On Mac ".
        "OS X, you might want to try Homebrew.");

      $restart_info = pht(
        "After installing new PHP extensions, <strong>restart your webserver ".
        "for the changes to take effect</strong>.");

      $description .= phutil_render_tag(
        'div',
        array(
          'class' => 'setup-issue-config',
        ),
        phutil_render_tag('p', array(), $install_these).
        phutil_render_tag('pre', array(), implode("\n", $extensions)).
        phutil_render_tag('p', array(), $install_info).
        phutil_render_tag('pre', array(), $install_commands).
        phutil_render_tag('p', array(), $fallback_info).
        phutil_render_tag('p', array(), $restart_info));

    }

    $next = phutil_render_tag(
      'div',
      array(
        'class' => 'setup-issue-next',
      ),
      pht('To continue, resolve this problem and reload the page.'));

    $name = phutil_render_tag(
      'div',
      array(
        'class' => 'setup-issue-name',
      ),
      phutil_escape_html($issue->getName()));

    return phutil_render_tag(
      'div',
      array(
        'class' => 'setup-issue',
      ),
      $name.$description.$next);
  }

  private function renderPhabricatorConfig(array $configs) {
    $issue = $this->getIssue();

    $table_info = phutil_render_tag(
      'p',
      array(),
      pht(
        "The current Phabricator configuration has these %d value(s):",
        count($configs)));

    $table = array();
    foreach ($configs as $key) {
      $table[] = '<tr>';
      $table[] = '<th>'.phutil_escape_html($key).'</th>';

      $value = PhabricatorEnv::getUnrepairedEnvConfig($key);
      if ($value === null) {
        $value = '<em>null</em>';
      } else if ($value === false) {
        $value = '<em>false</em>';
      } else if ($value === true) {
        $value = '<em>true</em>';
      } else {
        $value = phutil_escape_html(
          PhabricatorConfigJSON::prettyPrintJSON($value));
      }

      $table[] = '<td>'.$value.'</td>';
      $table[] = '</tr>';
    }

    $table = phutil_render_tag(
      'table',
      array(
      ),
      implode("\n", $table));

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();

    if ($this->getIssue()->getIsFatal()) {
      $update_info = phutil_render_tag(
        'p',
        array(),
        pht(
          "To update these %d value(s), run these command(s) from the command ".
          "line:",
          count($configs)));

      $update = array();
      foreach ($configs as $key) {
        $cmd = '<tt>phabricator/ $</tt> ./bin/config set '.
                phutil_escape_html($key).' '.
               '<em>value</em>';
        $update[] = $cmd;
      }
      $update = phutil_render_tag('pre', array(), implode("\n", $update));
    } else {
      $update = array();
      foreach ($configs as $config) {
        if (!idx($options, $config) || $options[$config]->getLocked()) {
          continue;
        }
        $link = phutil_render_tag(
          'a',
          array(
            'href' => '/config/edit/'.$config.'/?issue='.$issue->getIssueKey(),
          ),
          pht('Edit %s', phutil_escape_html($config)));
        $update[] = '<li>'.$link.'</li>';
      }
      if ($update) {
        $update = '<ul>'.implode("\n", $update).'</ul>';
        $update_info = phutil_render_tag(
          'p',
          array(),
          pht("You can update these %d value(s) here:", count($configs)));
      } else {
        $update = null;
        $update_info = null;
      }
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'setup-issue-config',
      ),
      self::renderSingleView(
        array(
          $table_info,
          $table,
          $update_info,
          $update,
        )));
  }

  private function renderPHPConfig(array $configs) {
    $table_info = phutil_render_tag(
      'p',
      array(),
      pht(
        "The current PHP configuration has these %d value(s):",
        count($configs)));

    $table = array();
    foreach ($configs as $key) {
      $table[] = '<tr>';
      $table[] = '<th>'.phutil_escape_html($key).'</th>';

      $value = ini_get($key);
      if ($value === null) {
        $value = '<em>null</em>';
      } else if ($value === false) {
        $value = '<em>false</em>';
      } else if ($value === true) {
        $value = '<em>true</em>';
      } else if ($value === '') {
        $value = '<em>(empty string)</em>';
      } else {
        $value = phutil_escape_html($value);
      }

      $table[] = '<td>'.$value.'</td>';
      $table[] = '</tr>';
    }

    $table = phutil_render_tag(
      'table',
      array(

      ),
      implode("\n", $table));

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

    if (!$ini_loc) {
      $info = phutil_render_tag(
        'p',
        array(),
        pht(
          "To update these %d value(s), edit your PHP configuration file.",
          count($configs)));
    } else {
      $info = phutil_render_tag(
        'p',
        array(),
        pht(
          "To update these %d value(s), edit your PHP configuration file, ".
          "located here:",
          count($configs)));
      $info .= phutil_render_tag(
        'pre',
        array(),
        phutil_escape_html($ini_loc));
    }

    if ($more_loc) {
      $info .= phutil_render_tag(
        'p',
        array(),
        pht(
          "PHP also loaded these configuration file(s):",
          count($more_loc)));
      $info .= phutil_render_tag(
        'pre',
        array(),
        phutil_escape_html(implode("\n", $more_loc)));
    }

    $info .= phutil_render_tag(
      'p',
      array(),
      pht(
        "You can find more information about PHP configuration values in the ".
        "%s.",
        phutil_render_tag(
          'a',
          array(
            'href' => 'http://php.net/manual/ini.list.php',
          ),
          pht('PHP Documentation'))));

    $info .= phutil_render_tag(
      'p',
      array(),
      pht(
        "After editing the PHP configuration, <strong>restart your ".
        "webserver for the changes to take effect</strong>."));

    return phutil_render_tag(
      'div',
      array(
        'class' => 'setup-issue-config',
      ),
      $table_info.$table.$info);
  }

}

<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorRepository extends PhabricatorRepositoryDAO {

  const TABLE_PATH = 'repository_path';
  const TABLE_PATHCHANGE = 'repository_pathchange';
  const TABLE_FILESYSTEM = 'repository_filesystem';
  const TABLE_SUMMARY = 'repository_summary';
  const TABLE_BADCOMMIT = 'repository_badcommit';

  protected $phid;
  protected $name;
  protected $callsign;
  protected $uuid;

  protected $versionControlSystem;
  protected $details = array();

  private $sshKeyfile;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_REPO);
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  public function getRemoteURI() {
    $raw_uri = $this->getDetail('remote-uri');

    $vcs = $this->getVersionControlSystem();
    $is_git = ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_GIT);

    // If there's no protocol (git implicit SSH) reformat the URI to be a
    // normal URI. These git URIs look like "user@domain.com:path" instead of
    // "ssh://user@domain/path".
    $uri = new PhutilURI($raw_uri);
    if ($is_git && !$uri->getProtocol()) {
      list($domain, $path) = explode(':', $raw_uri, 2);
      $uri = new PhutilURI('ssh://'.$domain.'/'.$path);
    }

    if ($this->isSSHProtocol($uri->getProtocol())) {
      if ($this->getSSHLogin()) {
        $uri->setUser($this->getSSHLogin());
      }
    }

    return (string)$uri;
  }

  public function getLocalPath() {
    return $this->getDetail('local-path');
  }

  public function execRemoteCommand($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return call_user_func_array('exec_manual', $args);
  }

  public function execxRemoteCommand($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return call_user_func_array('execx', $args);
  }

  public function getRemoteCommandFuture($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return newv('ExecFuture', $args);
  }

  public function passthruRemoteCommand($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return call_user_func_array('phutil_passthru', $args);
  }

  public function execLocalCommand($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('exec_manual', $args);
  }

  public function execxLocalCommand($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('execx', $args);
  }

  public function getLocalCommandFuture($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return newv('ExecFuture', $args);

  }
  public function passthruLocalCommand($pattern /*, $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('phutil_passthru', $args);
  }


  private function formatRemoteCommand(array $args) {
    $pattern = $args[0];
    $args = array_slice($args, 1);

    if ($this->shouldUseSSH()) {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $pattern = "SVN_SSH=%s svn --non-interactive {$pattern}";
          array_unshift(
            $args,
            csprintf(
              'ssh -l %s -i %s',
              $this->getSSHLogin(),
              $this->getSSHKeyfile()));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $command = call_user_func_array(
            'csprintf',
            array_merge(
              array(
                "(ssh-add %s && git {$pattern})",
                $this->getSSHKeyfile(),
              ),
              $args));
          $pattern = "ssh-agent sh -c %s";
          $args = array($command);
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $pattern = "hg --config ui.ssh=%s {$pattern}";
          array_unshift(
            $args,
            csprintf(
              'ssh -l %s -i %s',
              $this->getSSHLogin(),
              $this->getSSHKeyfile()));
          break;
        default:
          throw new Exception("Unrecognized version control system.");
      }
    } else if ($this->shouldUseHTTP()) {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $pattern =
            "svn ".
            "--non-interactive ".
            "--no-auth-cache ".
            "--trust-server-cert ".
            "--username %s ".
            "--password %s ".
            $pattern;
          array_unshift(
            $args,
            $this->getDetail('http-login'),
            $this->getDetail('http-pass'));
          break;
        default:
          throw new Exception(
            "No support for HTTP Basic Auth in this version control system.");
      }
    } else {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $pattern = "svn --non-interactive {$pattern}";
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $pattern = "git {$pattern}";
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $pattern = "hg {$pattern}";
          break;
        default:
          throw new Exception("Unrecognized version control system.");
      }
    }

    array_unshift($args, $pattern);

    return $args;
  }

  private function formatLocalCommand(array $args) {
    $pattern = $args[0];
    $args = array_slice($args, 1);

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $pattern = "(cd %s && svn --non-interactive {$pattern})";
        array_unshift($args, $this->getLocalPath());
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $pattern = "(cd %s && git {$pattern})";
        array_unshift($args, $this->getLocalPath());
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $pattern = "(cd %s && hg {$pattern})";
        array_unshift($args, $this->getLocalPath());
        break;
      default:
        throw new Exception("Unrecognized version control system.");
    }

    array_unshift($args, $pattern);

    return $args;
  }

  private function getSSHLogin() {
    return $this->getDetail('ssh-login');
  }

  private function getSSHKeyfile() {
    if ($this->sshKeyfile === null) {
      $key = $this->getDetail('ssh-key');
      $keyfile = $this->getDetail('ssh-keyfile');
      if ($keyfile) {
        // Make sure we can read the file, that it exists, etc.
        Filesystem::readFile($keyfile);
        $this->sshKeyfile = $keyfile;
      } else if ($key) {
        $keyfile = new TempFile('phabricator-repository-ssh-key');
        chmod($keyfile, 0600);
        Filesystem::writeFile($keyfile, $key);
        $this->sshKeyfile = $keyfile;
      } else {
        $this->sshKeyfile = '';
      }
    }

    return (string)$this->sshKeyfile;
  }

  public function shouldUseSSH() {
    $uri = new PhutilURI($this->getRemoteURI());
    $protocol = $uri->getProtocol();
    if ($this->isSSHProtocol($protocol)) {
      return (bool)$this->getSSHKeyfile();
    } else {
      return false;
    }
  }

  public function shouldUseHTTP() {
    $uri = new PhutilURI($this->getRemoteURI());
    $protocol = $uri->getProtocol();
    if ($this->isHTTPProtocol($protocol)) {
      return (bool)$this->getDetail('http-login');
    } else {
      return false;
    }
  }

  private function isSSHProtocol($protocol) {
    return ($protocol == 'ssh' || $protocol == 'svn+ssh');
  }

  private function isHTTPProtocol($protocol) {
    return ($protocol == 'http' || $protocol == 'https');
  }

}

<?php

final class ReleephBranchTemplate {

  const KEY = 'releeph.default-branch-template';

  public static function getDefaultTemplate() {
    return PhabricatorEnv::getEnvConfig(self::KEY);
  }

  public static function getRequiredDefaultTemplate() {
    $template = self::getDefaultTemplate();
    if (!$template) {
      throw new Exception(sprintf(
        "Config setting '%s' must be set, ".
        "or you must provide a branch-template for each project!",
        self::KEY));
    }
    return $template;
  }

  public static function getFakeCommitHandleFor($arc_project_id) {
    $arc_project = id(new PhabricatorRepositoryArcanistProject())
      ->load($arc_project_id);
    if (!$arc_project) {
      throw new Exception(
        "No Arc project found with id '{$arc_project_id}'!");
    }

    $repository = $arc_project->loadRepository();
    return id(new PhabricatorObjectHandle())
      ->setName($repository->formatCommitName('100000000000'));
  }

  private $commitHandle;
  private $branchDate = null;
  private $projectName;
  private $isSymbolic;

  public function setCommitHandle(PhabricatorObjectHandle $handle) {
    $this->commitHandle = $handle;
    return $this;
  }

  public function setBranchDate($branch_date) {
    $this->branchDate = $branch_date;
    return $this;
  }

  public function setReleephProjectName($project_name) {
    $this->projectName = $project_name;
    return $this;
  }

  public function setSymbolic($is_symbolic) {
    $this->isSymbolic = $is_symbolic;
    return $this;
  }

  public function interpolate($template) {
    if (!$this->projectName) {
      return array('', array());
    }

    list($name, $name_errors) = $this->interpolateInner(
      $template,
      $this->isSymbolic);

    if ($this->isSymbolic) {
      return array($name, $name_errors);
    } else {
      $validate_errors = $this->validateAsBranchName($name);
      $errors = array_merge($name_errors, $validate_errors);
      return array($name, $errors);
    }
  }

  public static function getHelpRemarkup() {
    return <<<EOTEXT

==== Interpolations ====

| Code  | Meaning
| ----- | -------
| `%P`  | The name of your project, with spaces changed to "-".
| `%p`  | Like %P, but all lowercase.
| `%Y`  | The four digit year associated with the branch date.
| `%m`  | The two digit month.
| `%d`  | The two digit day.
| `%v`  | The handle of the commit where the branch was cut ("rXYZa4b3c2d1").
| `%V`  | The abbreviated commit id where the branch was cut ("a4b3c2d1").
| `%..` | Any other sequence interpreted by `strftime()`.
| `%%`  | A literal percent sign.


==== Tips for Branch Templates ====

Use a directory to separate your release branches from other branches:

  lang=none
  releases/%Y-%M-%d-%v
  => releases/2012-30-16-rHERGE32cd512a52b7

Include a second hierarchy if you share your repository with other projects:

  lang=none
  releases/%P/%p-release-%Y%m%d-%V
  => releases/Tintin/tintin-release-20121116-32cd512a52b7

Keep your branch names simple, avoiding strange punctuation, most of which is
forbidden or escaped anyway:

  lang=none, counterexample
  releases//..clown-releases..//`date --iso=seconds`-$(sudo halt)

Include the date early in your template, in an order which sorts properly:

  lang=none
  releases/%Y%m%d-%v
  => releases/20121116-rHERGE32cd512a52b7 (good!)

  releases/%V-%m.%d.%Y
  => releases/32cd512a52b7-11.16.2012 (awful!)


EOTEXT
    ;
  }

  /*
   * xsprintf() would be useful here, but that's for formatting concrete lists
   * of things in a certain way...
   *
   *    animal_printf('%A %A %A', $dog1, $dog2, $dog3);
   *
   * ...rather than interpolating percent-control-strings like strftime does.
   */
  private function interpolateInner($template, $is_symbolic) {
    $name = $template;
    $errors = array();

    $safe_project_name = str_replace(' ', '-', $this->projectName);
    $short_commit_id = last(
      preg_split('/r[A-Z]+/', $this->commitHandle->getName()));

    $interpolations = array();
    for ($ii = 0; $ii < strlen($name); $ii++) {
      $char = substr($name, $ii, 1);
      $prev = null;
      if ($ii > 0) {
        $prev = substr($name, $ii - 1, 1);
      }
      $next = substr($name, $ii + 1, 1);
      if ($next && $char == '%' && $prev != '%') {
        $interpolations[$ii] = $next;
      }
    }

    $variable_interpolations = array();

    $reverse_interpolations = $interpolations;
    krsort($reverse_interpolations);

    if ($this->branchDate) {
      $branch_date = $this->branchDate;
    } else {
      $branch_date = $this->commitHandle->getTimestamp();
    }

    foreach ($reverse_interpolations as $position => $code) {
      $replacement = null;
      switch ($code) {
        case 'v':
          $replacement = $this->commitHandle->getName();
          $is_variable = true;
          break;

        case 'V':
          $replacement = $short_commit_id;
          $is_variable = true;
          break;

        case 'P':
          $replacement = $safe_project_name;
          $is_variable = false;
          break;

        case 'p':
          $replacement = strtolower($safe_project_name);
          $is_variable = false;
          break;

        default:
          // Format anything else using strftime()
          $replacement = strftime("%{$code}", $branch_date);
          $is_variable = true;
          break;
      }

      if ($is_variable) {
        $variable_interpolations[] = $code;
      }
      $name = substr_replace($name, $replacement, $position, 2);
    }

    if (!$is_symbolic && !$variable_interpolations) {
      $errors[] = "Include additional interpolations that aren't static!";
    }

    return array($name, $errors);
  }

  private function validateAsBranchName($name) {
    $errors = array();

    if (preg_match('{^/}', $name) || preg_match('{/$}', $name)) {
      $errors[] = "Branches cannot begin or end with '/'";
    }

    if (preg_match('{//+}', $name)) {
      $errors[] = "Branches cannot contain multiple consective '/'";
    }

    $parts = array_filter(explode('/', $name));
    foreach ($parts as $index => $part) {
      $part_error = null;
      if (preg_match('{^\.}', $part) || preg_match('{\.$}', $part)) {
        $errors[] = "Path components cannot begin or end with '.'";
      } elseif (preg_match('{^(?!\w)}', $part)) {
        $errors[] = "Path components must begin with an alphanumeric";
      } elseif (!preg_match('{^\w ([\w-_%\.]* [\w-_%])?$}x', $part)) {
        $errors[] =
          "Path components may only contain alphanumerics ".
          "or '-', '_', or '.'";
      }
    }

    return $errors;
  }

}

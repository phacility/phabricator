<?php

final class DifferentialLocalCommitsView extends AphrontView {

  private $localCommits;
  private $commitsForLinks = array();

  public function setLocalCommits($local_commits) {
    $this->localCommits = $local_commits;
    return $this;
  }

  public function setCommitsForLinks(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commitsForLinks = $commits;
    return $this;
  }

  public function render() {
    $user = $this->user;
    if (!$user) {
      throw new PhutilInvalidStateException('setUser');
    }

    $local = $this->localCommits;
    if (!$local) {
      return null;
    }

    $has_tree = false;
    $has_local = false;

    foreach ($local as $commit) {
      if (idx($commit, 'tree')) {
        $has_tree = true;
      }
      if (idx($commit, 'local')) {
        $has_local = true;
      }
    }

    $rows = array();
    foreach ($local as $commit) {
      $row = array();
      if (idx($commit, 'commit')) {
        $commit_link = $this->buildCommitLink($commit['commit']);
      } else if (isset($commit['rev'])) {
        $commit_link = $this->buildCommitLink($commit['rev']);
      } else {
        $commit_link = null;
      }
      $row[] = $commit_link;

      if ($has_tree) {
        $row[] = $this->buildCommitLink($commit['tree']);
      }

      if ($has_local) {
        $row[] = $this->buildCommitLink($commit['local']);
      }

      $parents = idx($commit, 'parents', array());
      foreach ($parents as $k => $parent) {
        if (is_array($parent)) {
          $parent = idx($parent, 'rev');
        }
        $parents[$k] = $this->buildCommitLink($parent);
      }
      $parents = phutil_implode_html(phutil_tag('br'), $parents);
      $row[] = $parents;

      $author = nonempty(
        idx($commit, 'user'),
        idx($commit, 'author'));
      $row[] = $author;

      $message = idx($commit, 'message');

      $summary = idx($commit, 'summary');
      $summary = id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(80)
        ->truncateString($summary);

      $view = new AphrontMoreView();
      $view->setSome($summary);

      if ($message && (trim($summary) != trim($message))) {
        $view->setMore(phutil_escape_html_newlines($message));
      }

      $row[] = $view->render();

      $date = nonempty(
        idx($commit, 'date'),
        idx($commit, 'time'));
      if ($date) {
        $date = phabricator_datetime($date, $user);
      }
      $row[] = $date;

      $rows[] = $row;
    }

    $column_classes = array('');
    if ($has_tree) {
      $column_classes[] = '';
    }
    if ($has_local) {
      $column_classes[] = '';
    }
    $column_classes[] = '';
    $column_classes[] = '';
    $column_classes[] = 'wide';
    $column_classes[] = 'date';
    $table = id(new AphrontTableView($rows))
      ->setColumnClasses($column_classes);
    $headers = array();
    $headers[] = pht('Commit');
    if ($has_tree) {
      $headers[] = pht('Tree');
    }
    if ($has_local) {
      $headers[] = pht('Local');
    }
    $headers[] = pht('Parents');
    $headers[] = pht('Author');
    $headers[] = pht('Summary');
    $headers[] = pht('Date');
    $table->setHeaders($headers);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Local Commits'))
      ->setTable($table);
  }

  private static function formatCommit($commit) {
    return substr($commit, 0, 12);
  }

  private function buildCommitLink($hash) {
    $commit_for_link = idx($this->commitsForLinks, $hash);
    $commit_hash = self::formatCommit($hash);
    if ($commit_for_link) {
      $link = phutil_tag(
        'a',
        array(
          'href' => $commit_for_link->getURI(),
        ),
        $commit_hash);
    } else {
      $link = $commit_hash;
    }
    return $link;
  }

}

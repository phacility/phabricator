<?php

final class DifferentialLocalCommitsView extends AphrontView {

  private $localCommits;

  public function setLocalCommits($local_commits) {
    $this->localCommits = $local_commits;
    return $this;
  }

  public function render() {
    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before render()-ing this view.");
    }

    $local = $this->localCommits;
    if (!$local) {
      return null;
    }

    require_celerity_resource('differential-local-commits-view-css');

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
    $highlight = true;
    foreach ($local as $commit) {
      if ($highlight) {
        $class = 'alt';
        $highlight = false;
      } else {
        $class = '';
        $highlight = true;
      }


      $row = array();
      if (idx($commit, 'commit')) {
        $commit_hash = self::formatCommit($commit['commit']);
      } else if (isset($commit['rev'])) {
        $commit_hash = self::formatCommit($commit['rev']);
      } else {
        $commit_hash = null;
      }
      $row[] = phutil_tag('td', array(), $commit_hash);

      if ($has_tree) {
        $tree = idx($commit, 'tree');
        $tree = self::formatCommit($tree);
        $row[] = phutil_tag('td', array(), $tree);
      }

      if ($has_local) {
        $local_rev = idx($commit, 'local', null);
        $row[] = phutil_tag('td', array(), $local_rev);
      }

      $parents = idx($commit, 'parents', array());
      foreach ($parents as $k => $parent) {
        if (is_array($parent)) {
          $parent = idx($parent, 'rev');
        }
        $parents[$k] = self::formatCommit($parent);
      }
      $parents = phutil_implode_html(phutil_tag('br'), $parents);
      $row[] = phutil_tag('td', array(), $parents);

      $author = nonempty(
        idx($commit, 'user'),
        idx($commit, 'author'));
      $row[] = phutil_tag('td', array(), $author);

      $message = idx($commit, 'message');

      $summary = idx($commit, 'summary');
      $summary = phutil_utf8_shorten($summary, 80);

      $view = new AphrontMoreView();
      $view->setSome($summary);

      if ($message && (trim($summary) != trim($message))) {
        $view->setMore(phutil_escape_html_newlines($message));
      }

      $row[] = phutil_tag(
        'td',
        array(
          'class' => 'summary',
        ),
        $view->render());

      $date = nonempty(
        idx($commit, 'date'),
        idx($commit, 'time'));
      if ($date) {
        $date = phabricator_datetime($date, $user);
      }
      $row[] = phutil_tag('td', array(), $date);

      $rows[] = phutil_tag('tr', array('class' => $class), $row);
    }


    $headers = array();
    $headers[] = phutil_tag('th', array(), pht('Commit'));
    if ($has_tree) {
      $headers[] = phutil_tag('th', array(), pht('Tree'));
    }
    if ($has_local) {
      $headers[] = phutil_tag('th', array(), pht('Local'));
    }
    $headers[] = phutil_tag('th', array(), pht('Parents'));
    $headers[] = phutil_tag('th', array(), pht('Author'));
    $headers[] = phutil_tag('th', array(), pht('Summary'));
    $headers[] = phutil_tag('th', array(), pht('Date'));

    $headers = phutil_tag('tr', array(), $headers);

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Local Commits'))
      ->render();

    return hsprintf(
      '%s'.
      '<div class="differential-panel">'.
        '<table class="differential-local-commits-table">%s%s</table>'.
      '</div>',
      $header,
      $headers,
      phutil_implode_html("\n", $rows));
  }

  private static function formatCommit($commit) {
    return substr($commit, 0, 12);
  }

}

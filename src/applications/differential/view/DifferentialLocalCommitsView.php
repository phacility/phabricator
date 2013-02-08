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
        $commit_hash = substr($commit['commit'], 0, 16);
      } else if (isset($commit['rev'])) {
        $commit_hash = substr($commit['rev'], 0, 16);
      } else {
        $commit_hash = null;
      }
      $row[] = phutil_tag('td', array(), $commit_hash);

      if ($has_tree) {
        $tree = idx($commit, 'tree');
        $tree = substr($tree, 0, 16);
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
        $parents[$k] = substr($parent, 0, 16);
      }
      $parents = array_interleave(phutil_tag('br'), $parents);
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
    $headers[] = '<th>'.pht('Commit').'</th>';
    if ($has_tree) {
      $headers[] = '<th>'.pht('Tree').'</th>';
    }
    if ($has_local) {
      $headers[] = '<th>'.pht('Local').'</th>';
    }
    $headers[] = '<th>'.pht('Parents').'</th>';
    $headers[] = '<th>'.pht('Author').'</th>';
    $headers[] = '<th>'.pht('Summary').'</th>';
    $headers[] = '<th>'.pht('Date').'</th>';

    $headers = '<tr>'.implode('', $headers).'</tr>';

    return
      id(new PhabricatorHeaderView())
        ->setHeader(pht('Local Commits'))
        ->render().
      '<div class="differential-panel">'.
        '<table class="differential-local-commits-table">'.
          $headers.
          implode("\n", $rows).
        '</table>'.
      '</div>';
  }
}

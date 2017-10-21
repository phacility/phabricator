<?php

/**
 * Given a commit and a path, efficiently determine the most recent ancestor
 * commit where the path was touched.
 *
 * In Git and Mercurial, log operations with a path are relatively slow. For
 * example:
 *
 *    git log -n1 <commit> -- <path>
 *
 * ...routinely takes several hundred milliseconds, and equivalent requests
 * often take longer in Mercurial.
 *
 * Unfortunately, this operation is fundamental to rendering a repository for
 * the web, and essentially everything else that's slow can be reduced to this
 * plus some trivial work afterward. Making this fast is desirable and powerful,
 * and allows us to make other things fast by expressing them in terms of this
 * query.
 *
 * Because the query is fundamentally a graph query, it isn't easy to express
 * in a reasonable way in MySQL, and we can't do round trips to the server to
 * walk the graph without incurring huge performance penalties.
 *
 * However, the total amount of data in the graph is relatively small. By
 * caching it in chunks and keeping it in APC, we can reasonably load and walk
 * the graph in PHP quickly.
 *
 * For more context, see T2683.
 *
 * Structure of the Cache
 * ======================
 *
 * The cache divides commits into buckets (see @{method:getBucketSize}). To
 * walk the graph, we pull a commit's bucket. The bucket is a map from commit
 * IDs to a list of parents and changed paths, separated by `null`. For
 * example, a bucket might look like this:
 *
 *   array(
 *     1 => array(0, null, 17, 18),
 *     2 => array(1, null, 4),
 *     // ...
 *   )
 *
 * This means that commit ID 1 has parent commit 0 (a special value meaning
 * no parents) and affected path IDs 17 and 18. Commit ID 2 has parent commit 1,
 * and affected path 4.
 *
 * This data structure attempts to balance compactness, ease of construction,
 * simplicity of cache semantics, and lookup performance. In the average case,
 * it appears to do a reasonable job at this.
 *
 * @task query Querying the Graph Cache
 * @task cache Cache Internals
 */
final class PhabricatorRepositoryGraphCache extends Phobject {

  private $rebuiltKeys = array();


/* -(  Querying the Graph Cache  )------------------------------------------- */


  /**
   * Search the graph cache for the most modification to a path.
   *
   * @param int     The commit ID to search ancestors of.
   * @param int     The path ID to search for changes to.
   * @param float   Maximum number of seconds to spend trying to satisfy this
   *                query using the graph cache. By default, `0.5` (500ms).
   * @return mixed  Commit ID, or `null` if no ancestors exist, or `false` if
   *                the graph cache was unable to determine the answer.
   * @task query
   */
  public function loadLastModifiedCommitID($commit_id, $path_id, $time = 0.5) {
    $commit_id = (int)$commit_id;
    $path_id = (int)$path_id;

    $bucket_data = null;
    $data_key = null;
    $seen = array();

    $t_start = microtime(true);
    $iterations = 0;
    while (true) {
      $bucket_key = $this->getBucketKey($commit_id);

      if (($data_key != $bucket_key) || $bucket_data === null) {
        $bucket_data = $this->getBucketData($bucket_key);
        $data_key = $bucket_key;
      }

      if (empty($bucket_data[$commit_id])) {
        // Rebuild the cache bucket, since the commit might be a very recent
        // one that we'll pick up by rebuilding.

        $bucket_data = $this->getBucketData($bucket_key, $bucket_data);
        if (empty($bucket_data[$commit_id])) {
          // A rebuild didn't help. This can occur legitimately if the commit
          // is new and hasn't parsed yet.
          return false;
        }

        // Otherwise, the rebuild gave us the data, so we can keep going.

        $did_fill = true;
      } else {
        $did_fill = false;
      }

      // Sanity check so we can survive and recover from bad data.
      if (isset($seen[$commit_id])) {
        phlog(pht('Unexpected infinite loop in %s!', __CLASS__));
        return false;
      } else {
        $seen[$commit_id] = true;
      }

      // `$data` is a list: the commit's parent IDs, followed by `null`,
      // followed by the modified paths in ascending order. We figure out the
      // first parent first, then check if the path was touched. If the path
      // was touched, this is the commit we're after. If not, walk backward
      // in the tree.

      $items = $bucket_data[$commit_id];
      $size = count($items);

      // Walk past the parent information.
      $parent_id = null;
      for ($ii = 0;; ++$ii) {
        if ($items[$ii] === null) {
          break;
        }
        if ($parent_id === null) {
          $parent_id = $items[$ii];
        }
      }

      // Look for a modification to the path.
      for (; $ii < $size; ++$ii) {
        $item = $items[$ii];
        if ($item > $path_id) {
          break;
        }
        if ($item === $path_id) {
          return $commit_id;
        }
      }

      if ($parent_id) {
        $commit_id = $parent_id;

        // Periodically check if we've spent too long looking for a result
        // in the cache, and return so we can fall back to a VCS operation.
        // This keeps us from having a degenerate worst case if, e.g., the
        // cache is cold and we need to inspect a very large number of blocks
        // to satisfy the query.

        ++$iterations;

        // If we performed a cache fill in this cycle, always check the time
        // limit, since cache fills may take a significant amount of time.

        if ($did_fill || ($iterations % 64 === 0)) {
          $t_end = microtime(true);
          if (($t_end - $t_start) > $time) {
            return false;
          }
        }
        continue;
      }

      // If we have an explicit 0, that means this commit really has no parents.
      // Usually, it is the first commit in the repository.
      if ($parent_id === 0) {
        return null;
      }

      // If we didn't find a parent, the parent data isn't available. We fail
      // to find an answer in the cache and fall back to querying the VCS.
      return false;
    }
  }


/* -(  Cache Internals  )---------------------------------------------------- */


  /**
   * Get the bucket key for a given commit ID.
   *
   * @param   int   Commit ID.
   * @return  int   Bucket key.
   * @task cache
   */
  private function getBucketKey($commit_id) {
    return (int)floor($commit_id / $this->getBucketSize());
  }


  /**
   * Get the cache key for a given bucket key (from @{method:getBucketKey}).
   *
   * @param   int     Bucket key.
   * @return  string  Cache key.
   * @task cache
   */
  private function getBucketCacheKey($bucket_key) {
    static $prefix;

    if ($prefix === null) {
      $self = get_class($this);
      $size = $this->getBucketSize();
      $prefix = "{$self}:{$size}:2:";
    }

    return $prefix.$bucket_key;
  }


  /**
   * Get the number of items per bucket.
   *
   * @return  int Number of items to store per bucket.
   * @task cache
   */
  private function getBucketSize() {
    return 4096;
  }


  /**
   * Retrieve or build a graph cache bucket from the cache.
   *
   * Normally, this operates as a readthrough cache call. It can also be used
   * to force a cache update by passing the existing data to `$rebuild_data`.
   *
   * @param   int     Bucket key, from @{method:getBucketKey}.
   * @param   mixed   Current data, to force a cache rebuild of this bucket.
   * @return  array   Data from the cache.
   * @task cache
   */
  private function getBucketData($bucket_key, $rebuild_data = null) {
    $cache_key = $this->getBucketCacheKey($bucket_key);

    // TODO: This cache stuff could be handled more gracefully, but the
    // database cache currently requires values to be strings and needs
    // some tweaking to support this as part of a stack. Our cache semantics
    // here are also unusual (not purely readthrough) because this cache is
    // appendable.

    $cache_level1 = PhabricatorCaches::getRepositoryGraphL1Cache();
    $cache_level2 = PhabricatorCaches::getRepositoryGraphL2Cache();
    if ($rebuild_data === null) {
      $bucket_data = $cache_level1->getKey($cache_key);
      if ($bucket_data) {
        return $bucket_data;
      }

      $bucket_data = $cache_level2->getKey($cache_key);
      if ($bucket_data) {
        $unserialized = @unserialize($bucket_data);
        if ($unserialized) {
          // Fill APC if we got a database hit but missed in APC.
          $cache_level1->setKey($cache_key, $unserialized);
          return $unserialized;
        }
      }
    }

    if (!is_array($rebuild_data)) {
      $rebuild_data = array();
    }

    $bucket_data = $this->rebuildBucket($bucket_key, $rebuild_data);

    // Don't bother writing the data if we didn't update anything.
    if ($bucket_data !== $rebuild_data) {
      $cache_level2->setKey($cache_key, serialize($bucket_data));
      $cache_level1->setKey($cache_key, $bucket_data);
    }

    return $bucket_data;
  }


  /**
   * Rebuild a cache bucket, amending existing data if available.
   *
   * @param   int     Bucket key, from @{method:getBucketKey}.
   * @param   array   Existing bucket data.
   * @return  array   Rebuilt bucket data.
   * @task cache
   */
  private function rebuildBucket($bucket_key, array $current_data) {

    // First, check if we've already rebuilt this bucket. In some cases (like
    // browsing a repository at some commit) it's common to issue many lookups
    // against one commit. If that commit has been discovered but not yet
    // fully imported, we'll repeatedly attempt to rebuild the bucket. If the
    // first rebuild did not work, subsequent rebuilds are very unlikely to
    // have any effect. We can just skip the rebuild in these cases.

    if (isset($this->rebuiltKeys[$bucket_key])) {
      return $current_data;
    } else {
      $this->rebuiltKeys[$bucket_key] = true;
    }

    $bucket_min = ($bucket_key * $this->getBucketSize());
    $bucket_max = ($bucket_min + $this->getBucketSize()) - 1;

    // We need to reload all of the commits in the bucket because there is
    // no guarantee that they'll get parsed in order, so we can fill large
    // commit IDs before small ones. Later on, we'll ignore the commits we
    // already know about.

    $table_commit = new PhabricatorRepositoryCommit();
    $table_repository = new PhabricatorRepository();
    $conn_r = $table_commit->establishConnection('r');

    // Find all the Git and Mercurial commits in the block which have completed
    // change import. We can't fill the cache accurately for commits which have
    // not completed change import, so just pretend we don't know about them.
    // In these cases, we will ultimately fall back to VCS queries.

    $commit_rows = queryfx_all(
      $conn_r,
      'SELECT c.id FROM %T c
        JOIN %T r ON c.repositoryID = r.id AND r.versionControlSystem IN (%Ls)
        WHERE c.id BETWEEN %d AND %d
          AND (c.importStatus & %d) = %d',
      $table_commit->getTableName(),
      $table_repository->getTableName(),
      array(
        PhabricatorRepositoryType::REPOSITORY_TYPE_GIT,
        PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL,
      ),
      $bucket_min,
      $bucket_max,
      PhabricatorRepositoryCommit::IMPORTED_CHANGE,
      PhabricatorRepositoryCommit::IMPORTED_CHANGE);

    // If we don't have any data, just return the existing data.
    if (!$commit_rows) {
      return $current_data;
    }

    // Remove the commits we already have data for. We don't need to rebuild
    // these. If there's nothing left, return the existing data.

    $commit_ids = ipull($commit_rows, 'id', 'id');
    $commit_ids = array_diff_key($commit_ids, $current_data);

    if (!$commit_ids) {
      return $current_data;
    }

    // Find all the path changes for the new commits.
    $path_changes = queryfx_all(
      $conn_r,
      'SELECT commitID, pathID FROM %T
        WHERE commitID IN (%Ld)
        AND (isDirect = 1 OR changeType = %d)',
      PhabricatorRepository::TABLE_PATHCHANGE,
      $commit_ids,
      DifferentialChangeType::TYPE_CHILD);
    $path_changes = igroup($path_changes, 'commitID');

    // Find all the parents for the new commits.
    $parents = queryfx_all(
      $conn_r,
      'SELECT childCommitID, parentCommitID FROM %T
        WHERE childCommitID IN (%Ld)
        ORDER BY id ASC',
      PhabricatorRepository::TABLE_PARENTS,
      $commit_ids);
    $parents = igroup($parents, 'childCommitID');

    // Build the actual data for the cache.
    foreach ($commit_ids as $commit_id) {
      $parent_ids = array();
      if (!empty($parents[$commit_id])) {
        foreach ($parents[$commit_id] as $row) {
          $parent_ids[] = (int)$row['parentCommitID'];
        }
      } else {
        // We expect all rows to have parents (commits with no parents get
        // an explicit "0" placeholder). If we're in an older repository, the
        // parent information might not have been populated yet. Decline to fill
        // the cache if we don't have the parent information, since the fill
        // will be incorrect.
        continue;
      }

      if (isset($path_changes[$commit_id])) {
        $path_ids = $path_changes[$commit_id];
        foreach ($path_ids as $key => $path_id) {
          $path_ids[$key] = (int)$path_id['pathID'];
        }
        sort($path_ids);
      } else {
        $path_ids = array();
      }

      $value = $parent_ids;
      $value[] = null;
      foreach ($path_ids as $path_id) {
        $value[] = $path_id;
      }

      $current_data[$commit_id] = $value;
    }

    return $current_data;
  }

}

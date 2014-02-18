/* Mark all existing password hashes as "Iterated MD5". */

UPDATE {$NAMESPACE}_user.user
  SET passwordHash = CONCAT('md5:', passwordHash)
  WHERE LENGTH(passwordHash) > 0;

ALTER TABLE {$NAMESPACE}_project.project
  ADD isMembershipLocked TINYINT(1) NOT NULL DEFAULT 0 AFTER joinPolicy;

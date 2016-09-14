/* Allow this column to be nullable (null means we'll try to autodetect) */
ALTER TABLE {$NAMESPACE}_pastebin.pastebin_paste MODIFY language VARCHAR(64)
    COLLATE {$COLLATE_TEXT};

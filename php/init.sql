CREATE TABLE IF NOT EXISTS `ttrss_pusher` (
    url_hash VARCHAR(40) PRIMARY KEY,
    last_accessed TIMESTAMP NOT NULL
);

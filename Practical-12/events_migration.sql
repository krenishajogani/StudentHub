ALTER TABLE events
    ADD COLUMN poster_data LONGBLOB NULL,
    ADD COLUMN poster_mime VARCHAR(32) NULL,
    ADD COLUMN poster_name VARCHAR(255) NULL;
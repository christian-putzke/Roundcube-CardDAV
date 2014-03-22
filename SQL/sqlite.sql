CREATE TABLE carddav_contacts (
    carddav_contact_id integer NOT NULL PRIMARY KEY,
    carddav_server_id  integer NOT NULL,
    user_id            integer NOT NULL,
    etag               VARCHAR(64) NOT NULL,
    last_modified      VARCHAR(128) NOT NULL,
    vcard_id           VARCHAR(64) NOT NULL,
    vcard              TEXT NOT NULL,
    words              TEXT NOT NULL,
    firstname          varchar(128) DEFAULT NULL,
    surname            varchar(128) DEFAULT NULL,
    name               varchar(255) DEFAULT NULL,
    email              varchar(255) DEFAULT NULL,

    UNIQUE(carddav_server_id,user_id,vcard_id),
    -- not enforced by sqlite < 3.6.19
    FOREIGN KEY(carddav_server_id) REFERENCES carddav_server(carddav_server_id) ON DELETE CASCADE
);

CREATE TABLE carddav_server (
    carddav_server_id  integer NOT NULL PRIMARY KEY,
    user_id            integer NOT NULL,
    url                varchar(255) NOT NULL,
    username           VARCHAR(128) NOT NULL,
    password           VARCHAR(128) NOT NULL,
    label              VARCHAR(128) NOT NULL,
    read_only          TINYINT NOT NULL,

    -- not enforced by sqlite < 3.6.19
    FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE INDEX userid_dav_idx1 ON carddav_contacts(user_id);
CREATE INDEX userid_dav_idx2 ON carddav_server(user_id);


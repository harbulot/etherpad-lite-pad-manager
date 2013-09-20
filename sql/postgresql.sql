CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  openid varchar(128) NOT NULL UNIQUE,
  nickname varchar(128) DEFAULT NULL,
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  logged timestamptz DEFAULT NULL,
  is_manager integer NOT NULL DEFAULT 0,
  is_enabled integer NOT NULL DEFAULT 0
);

CREATE TABLE sessions (
  id varchar(32) PRIMARY KEY,
  data text,
  last_access integer DEFAULT NULL
);

CREATE TABLE log (
  user_id integer NOT NULL REFERENCES users(id),
  logged timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address varchar(128) NOT NULL,
  useragent varchar(1024) DEFAULT NULL
);
CREATE INDEX log_user_id_idx ON log(user_id);

CREATE TABLE autologin (
  user_id integer NOT NULL REFERENCES users(id),
  secret char(32) NOT NULL,
  expires integer NOT NULL
);
CREATE INDEX autologin_user_id_idx ON autologin(user_id, secret);
CREATE INDEX autologin_expires_idx ON autologin(expires);

CREATE TABLE store (
  key varchar(100) NOT NULL PRIMARY KEY,
  "value" text NOT NULL
);

CREATE TABLE pads (
  id varchar(128) PRIMARY KEY,
  name varchar(128) NOT NULL,
  is_private integer NOT NULL DEFAULT 0,
  user_id integer NOT NULL REFERENCES users(id)
);
CREATE INDEX pads_user_idx ON pads(user_id);



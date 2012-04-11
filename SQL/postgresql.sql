CREATE TABLE IF NOT EXISTS "carddav_server" (
	"carddav_server_id" serial,
	"user_id" int NOT NULL REFERENCES "users" ON DELETE CASCADE,
	"url" varchar(255) NOT NULL,
	"username" varchar(128) NOT NULL,
	"password" varchar(128) NOT NULL,
	"label" varchar(128) NOT NULL,
	"read_only" int NOT NULL,
	PRIMARY KEY ("carddav_server_id")
);

CREATE TABLE IF NOT EXISTS "carddav_contacts" (
	"carddav_contact_id" serial,
	"carddav_server_id" int REFERENCES "carddav_server" ON DELETE CASCADE,
	"user_id" int,
	"etag" varchar(64) NOT NULL,
	"last_modified" varchar(128) NOT NULL,
	"vcard_id" varchar(64),
	"vcard" text NOT NULL,
	"words" text,
	"firstname" varchar(128) DEFAULT NULL,
	"surname" varchar(128) DEFAULT NULL,
	"name" varchar(255) DEFAULT NULL,
	"email" varchar(255) DEFAULT NULL,
	PRIMARY KEY ("carddav_server_id","user_id","vcard_id")
);

CREATE INDEX "user_id" ON "carddav_contacts" ("user_id");
-- Make office color nullable and remove unique constraint
ALTER TABLE offices DROP INDEX UNIQ_F574FF4C665648E9;
ALTER TABLE offices MODIFY color VARCHAR(7) DEFAULT NULL;

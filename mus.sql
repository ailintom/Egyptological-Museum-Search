-- Egyptological Museum Search ver. 0.7.1
-- Author: Alexander Ilin-Tomich
-- Created at Johannes Gutenberg University, Mainz
-- Date: 08.12.2016
-- Licensed under Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0) license
-- https://creativecommons.org/licenses/by-sa/4.0/
-- Includes code snippets originally posted under CC BY-SA 3.0 (attributed to the respective authors in the comments below)


CREATE TABLE IF NOT EXISTS `invs` (
  `id` int(11) DEFAULT NULL,
  `webid` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `inv` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `mus` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `uid` int(11) NOT NULL DEFAULT '0',
  KEY `mus` (`mus`),
  KEY `inv` (`inv`),
  KEY `webid` (`webid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Removes non-digits from a string
-- Based on code-snippets posted by user1467716 and wally 
-- on http://stackoverflow.com/questions/287105/mysql-strip-non-numeric-characters-to-compare 
-- under  CC BY-SA 3.0
DELIMITER $$
CREATE FUNCTION `STRIP_NON_DIGIT`(input VARCHAR(255)) RETURNS varchar(255) CHARSET utf8
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN
   DECLARE output    VARCHAR(255) DEFAULT '';
   DECLARE inp    VARCHAR(255) DEFAULT '';
   DECLARE iterator  INT          DEFAULT 1;
   DECLARE lastDigit INT          DEFAULT 1;
   DECLARE len       INT;
   SET inp = input;
IF inp like "%/%" THEN 
SET inp = TRIM( SUBSTRING( inp, 1, LOCATE("/", inp)-1));
END IF;
   SET len = LENGTH(inp) + 1;
   WHILE iterator < len DO
      -- skip past all digits
      SET lastDigit = iterator;
      WHILE ORD(SUBSTRING(inp, iterator, 1)) BETWEEN 48 AND 57 AND iterator < len DO
         SET iterator = iterator + 1;
      END WHILE;

      IF iterator != lastDigit THEN
         SET output = CONCAT(output, SUBSTRING(inp, lastDigit, iterator - lastDigit));
      END IF;

      WHILE ORD(SUBSTRING(inp, iterator, 1)) NOT BETWEEN 48 AND 57 AND iterator < len DO
         SET iterator = iterator + 1;
      END WHILE;
   END WHILE;

   RETURN output;
END$$
DELIMITER ;
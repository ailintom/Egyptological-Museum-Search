Egyptological Museum Search
A PHP tool to search the objects by inventory numbers in various online museum catalogues
Author: Alexander Ilin-Tomich
Created at Johannes Gutenberg University, Mainz
 Date: 08.12.2016
 Licensed under Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0) license
 https://creativecommons.org/licenses/by-sa/4.0/
 Includes code snippets originally posted under CC BY-SA 3.0 on http://stackoverflow.com
 (original authors: Stanislav Shabalin, user1467716 and wally) as indicated in the comments to the respective parts
 of the source code.

  Egyptological Museum Search operates on http://static.egyptology.ru/varia/mus.php

  Source files are available on https://github.com/ailintom/Egyptological-Museum-Search 
 
  This php script should be used as follows:
  .../mus.php?museum=AAA&no=###
  Where AAA is the museum name and ### is the object's accession number.

  The script was developed for PHP 5.3 and was tested to work with a MySQL-compatible Percona Database on an Apache server of a shared web-hosting.
  
  It operates in different modes depending on the architecture of each museum's website. 
  It aims to form correct search URLs for museums websites, which support GET queries.
  It retrieves search results in JSON from the museums, which provide this option.  
  It relies on the information scraped from the museum catalogues that do not support GET queries and do not provide search results
  in JSON. For this end, the script connects to a MySQL database with the scraped information. (The scrape scripts written in R are not included in the current version of the program source).
  The database can be created using mus.sql. The script connects to it using on the credentials in musconfig.json. 
  The scraped data should be stored in the table invs  with the following fields:
    mus - the name of the museum coinciding, as defined in the museumdefinitions.json
    inv - the inventory number
    webid - the number used to form the URL of the object description page. 

  The configuration files are json arrays musaliases.json, musconfig.json, museumdefinitions.json.
  Their structure is defined in the comments in mus.php. 
  
  The tool was designed with Egyptological collections in mind. If you with to adapt it to work with other collections, please mind the "Egyptology-specific" comments in the source code.
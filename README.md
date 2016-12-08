Egyptological-Museum-Search
A PHP tool to search the objects by inventory numbers in various online museum catalogues
Author: Alexander Ilin-Tomich
Created at Johannes Gutenberg University, Mainz
 Date: 08.12.2016
 Licensed under Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0) license
 https://creativecommons.org/licenses/by-sa/4.0/
 Includes code snippets originally posted under CC BY-SA 3.0 on http://stackoverflow.com
 (original authors: Stanislav Shabalin, user1467716 and wally) as indicated in the comments to the respective parts
 of the source code.
 
  This php script should be used as follows:
  .../mus.php?museum=AAA&no=###
  Where AAA is the museum name and ### is the object's accession number.
  
  It operates in different modes depending on the museum website. 
  It aims to form correct search URLs for museums websites, which support GET queries.
  It retrieves search results in JSON from the museums, which provide this option.  
  It relies on information scraped from the museum catalogues, which do not support GET queries and do not provide search results
  in JSON. For this end, the scipt connects to a MySQL database containing a table invs (created using mus.sql) with the following fields:
  mus - the name of the museum coinciding, as defined in the museumdefinitions.json
  inv - the inventory number
  webid - the number used to form the URL of the object description page. 

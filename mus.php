<?php
/**
 * Egyptological Museum Search ver. 0.7.2
 * Author: Alexander Ilin-Tomich
 * Created at Johannes Gutenberg University, Mainz
 * Date: 20.12.2016
 * Licensed under Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0) license
 * https://creativecommons.org/licenses/by-sa/4.0/
 * Includes code snippets originally posted under CC BY-SA 3.0 (attributed to the respective authors in the comments below)


 * Version history:
 * Version 0.1: 28.05.2015
 * Version 0.2: 14.11.2016
 * Version 0.3: 22.11.2016
 * * First public release
 * Version 0.4: 24.11.2016
 * * Improved support for MMA (fetching JSON files and redirecting directly to object pages)
 * Version 0.5: 01.12.2016
 * * Added support for OIM, Jerusalem and Ny Carlsberg, improved MMA search
 *  Version 0.6: 04.12.2016
 * * Added support for Edinburgh. Improvements in MMA and Louvre search
 *  Version 0.7: 07.12.2016
 * * Improved code structure. Minor bug fixes.
 *  Version 0.7.1: 08.12.2016
 * * Sanitizing input for SQL Queries. Minor improvements for Brooklyn and Boston. First GitHub release
 *  Version 0.7.2: 20.12.2016
 * * Numerous improvements in accession number processing
 *  Version 0.7.3: 21.08.2017
 * * Updated to work with the new BM SPARQL interface and the Fitzwilliam API
 *  *  Version 0.8: 09.03.2019
 * * Added Bristol
 *  *  Version 0.9: 23.04.2019
 * * Added Berlin
 * Version 0.9.1: 15.07.2023 updated Edinburgh, Louvre, and other museums
 * Version 0.9.2: 16.07.2023 added Glasgow Burrell and Glasgow Kelvingrove
 * Version 0.9.3: 16.07.2023 temporarily removed the use of the BM SPARQL endpoint, because it is very slow
 * Version 0.9.4: 14.11.2023 fixed Budapest, ISAC
 * Version 0.9.8: 01.10.2024 fixed Manchester, Liverpool, Allard Pierson
 * 
 * 
 * This php script should be used as follows:
 * .../mus.php?museum=AAA&no=###
 * Where AAA is the museum name and ### is the object's accession number.
 * 
 * *  The array of MySQL connection settings is read from the configuration file musconfig.json into $musconfig
 * Each museum alias definition is itself an array:
 * $musconfig[0] - host
 * $musconfig[1] - user
 * $musconfig[2] - password
 * $musconfig[3] - database name
 * $musconfig[4] - agent string for curl
 * $musconfig[5] - impressum string
 * $musconfig[6] - HTML Header
 * $musconfig[7] - Website title
 * 
 * The array of museum definitions is read from the configuration file museumdefinitions.json into $musarray
 * Each museum definition is itself an array:
 * musarray[0] - museum name
 * musarray[1] - url before the acc. no
 * musarray[2] - url after the acc. no
 * musarray[3] - should the museum name in the query be equal to the defined name (true) or just contain it (false)
 * musarray[4] - this museum name should not appear in the select box on the main page
 * musarray[5] - true=this museum website is not searchable with HTTP GET queries and local database with scraped data should be used instead; false=redirect to the search URL on the museum website
 * musarray[6] - url to be loaded if record is not found in the local databse
 * musarray[7] - full name of the museum to be displayed on the Help (.../mus.php?help=help page)
 * 
 * 
 * The array of name aliases is read from the configuration file musaliases.json into $musaliases
 * Each museum alias definition is itself an array:
 * $musaliases[0] - museum alias
 * $musaliases[1] - museum identifier used in museumdefinitions.json / $musarray
 * $musaliases[2] - should the museum name in the query be equal to the defined alias (true) or just contain it (false)
 * 
 * The code incorporates code snippets from various http://stackoverflow.com discussions, as indicated in the comments
 */

/** Returns the location of the first digit in a string
 * This function is based on a code snippet published by Stanislav Shabalin on http://stackoverflow.com/questions/7495603/find-the-position-of-the-first-occurring-of-any-number-in-string-php 
  under the cc by-sa 3.0  license */
function firstnum($text) {

    preg_match('/\d/', $text, $match, PREG_OFFSET_CAPTURE);
    if (sizeof($match)) {
        return $match[0][1];
    } else {
        return false;
    }
}

function showcss() {
    ?> <style type="text/css"> .limit {
            max-width: 720px;
        }
        html, body {
            font-family: sans-serif;
            font-size: 16px;
        }
        td {
            padding-top: 9px;
            padding-bottom: 9px;
        }
        .overlay {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            visibility: hidden;
            opacity: 0;
        }
        .overlay:target {
            visibility: visible;
            opacity: 1;
        }

        .popup {
            margin: 60px auto;
            padding: 15px;
            background: #fff;
            border-radius: 5px;
            width: 50%;
            position: relative;


        }
        .popup .close {
            position: absolute;
            top: 20px;
            right: 30px;
            transition: all 200ms;
            font-size: 30px;
            font-weight: bold;
            text-decoration: none;
            color: #333;
        }
        .popup .close:hover {
            color: #06D85F;
        }
        .popup .content {
            max-height: 30%;
            overflow: auto;
        }</style><?php
    }

    /** Returns the location of the first non-digit in a string
     * This function is based on a code snippet published by Stanislav Shabalin on http://stackoverflow.com/questions/7495603/find-the-position-of-the-first-occurring-of-any-number-in-string-php 
      under the cc by-sa 3.0  license */
    function firstnonnum($text) {
        preg_match('/\D/', $text, $match, PREG_OFFSET_CAPTURE);
        if (sizeof($match)) {
            return $match[0][1];
        } else {
            return false;
        }
    }

    /** Returns the text file downloaded from $url with curl */
    function downloadmusjson($url) {
//  Initiate curl
        $curl_handle = curl_init();
// Disable SSL verification
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
// Return the response, if false it print the response
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
// Set the url
        curl_setopt($curl_handle, CURLOPT_URL, $url);
// Set the user-agent 
        $musconfig = json_decode(file_get_contents("musconfig.json"), true);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, $musconfig[4]);
// Execute
        $res = curl_exec($curl_handle);
// Closing
        curl_close($curl_handle);
        return $res;
    }

    /** Returns the text file downloaded from $url with curl */
    function downloadmusjsonpost($url, $data) {
//  Initiate curl
        $curl_handle = curl_init();
// Disable SSL verification
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
// 
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        //Set headers
        $headers = array();
        $headers[] = 'Accept: application/sparql-results+json';
        $headers[] = 'Content-Type: application/sparql-query; charset=utf-8';
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl_handle, CURLOPT_POST, 1);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data);
// Set the url
        curl_setopt($curl_handle, CURLOPT_URL, $url);
// Set the user-agent 
        $musconfig = json_decode(file_get_contents("musconfig.json"), true);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, $musconfig[4]);
// Execute
        $res = curl_exec($curl_handle);
// Closing
        curl_close($curl_handle);

        return $res;
    }

    /** Displays a page with two embedded pages for the results from two different web catalogues */
    function DualPage($url1, $url2) {
        ?>
    <!DOCTYPE html PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'><html><head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8'><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php
            $musconfig = json_decode(file_get_contents("musconfig.json"), true);
            echo($musconfig[6]);
            showcss();
            ?></head><body><div style='position: absolute; height: 100%; border: none;width:100%;overflow-y: hidden;'>
                <div id='LeftPart' style='height: 100%;float:left;width:50%;background-color:#FFFFFF; overflow-y: hidden;'><object type='text/html' data='<?php echo($url1); ?>' width='100%' height='100%' >
                <!--[if lte IE 8]><iframe src='<?php echo($url1); ?>' width='100%' height='100%' >[Your browser does not support embedded objects. Please, visit<A href='<?php echo($url1); ?>'>the search page.</iframe><![endif]-->
                        [Your browser does not support embedded objects. Please, visit <A href='<?php echo($url1); ?>'>the search page.</A>]</object></div>
                <div id='RightPart' style='height: 100%;float:right;width:50%;background-color:#FFFFFF;overflow-y: hidden;'><object type='text/html' data='<?php echo($url2); ?>' width='100%' height='100%' >
                <!--[if lte IE 8]><iframe src='<?php echo($url2); ?>' width='100%' height='100%' >[Your browser does not support embedded objects. Please, visit<A href='<?php echo($url2); ?>'>the search page.</iframe><![endif]-->
                        [Your browser does not support embedded objects. Please, visit <A href='<?php echo($url2); ?>'>the search page.</A>]</object></div></div></body></html>
    <?php
    exit();
}

/** Executes a query in the local database, handles errors and returns a mysqli_result object
 */
function mySQLqueryex($mus, $searchfieldop, $sqlWHERE) {

    $musconfig = json_decode(file_get_contents("musconfig.json"), true);
    $mysqli = new mysqli($musconfig[0], $musconfig[1], $musconfig[2], $musconfig[3]);
    if ($mysqli->connect_errno) {
        ?>
        <!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'> <html> <head><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php
                $musconfig = json_decode(file_get_contents("musconfig.json"), true);
                echo($musconfig[6]);
                ?></head> <body><p>
                    Error: Failed to make a MySQL connection. Error details: 
                </p><p>Errno: <?php echo($mysqli->connect_errno); ?> 
                </p><p>Error: <?php echo($mysqli->connect_error); ?> 
                </p><p> Return to <a href='./mus.php'><?php echo($musconfig[7]); ?></a> or reload this page.</p><p>&nbsp;<a href="./mus.php?help=impressum">Impressum</a></p></body></html>
        <?php
        exit();
    }
    $sql = "SELECT webid, inv FROM invs WHERE mus = '" . $mus . "' and " . $searchfieldop . "'" . $mysqli->real_escape_string($sqlWHERE) . "' ORDER BY inv";
    if (!$result = $mysqli->query($sql)) {
        ?>
        <!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>
        <html> <head><?php
                $musconfig = json_decode(file_get_contents("musconfig.json"), true);
                echo($musconfig[6]);
                ?></head><body>
                <p>Error: The query failed to execute. Error details:  </p>
                <p>Errno: <?php echo($mysqli->errno); ?></p>
                <p>Error: <?php echo($mysqli->error); ?></p>
                <p>Return to <a href='/mus.php'><?php echo($musconfig[7]); ?></a> or reload this page</p><p>&nbsp;<a href="./mus.php?help=impressum">Impressum</a></p></body></html>
        <?php
        exit();
    }

    $mysqli->close();
    return $result;
}

/** Redirects to the single search result or displays a page to choose one of the multiple results in a mysqli_result object with webids
 * $result - a mysqli_result object
 * $pref - the part of of the object display page URL before the webid
 * $postf - the part of of the object display page URL after the webid
 * $mus - museum name
 */
function ReturnResults($result, $pref, $postf, $mus) {
    if ($result->num_rows == 1) {
        $webid = $result->fetch_assoc();
        $url = "http://" . $pref . $webid["webid"] . $postf;
        RedirUrl($url);
    } elseif ($result->num_rows > 1) {
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $resarray[] = $row;
        }
        ReturnResultsFromArray($resarray, $pref, $postf, $mus);
    }
    exit();
}

/** Displays a page to choose one of the multiple results in a $res array or redirects to the page with the single result
 * $result - a mysqli_result object
 * $pref - the part of of the object display page URL before the webid
 * $postf - the part of of the object display page URL after the webid
 * $mus - museum name */
function ReturnResultsFromArray($res, $pref, $postf, $mus) {
    if (sizeof($res) == 1) {
        $url = "http://" . $pref . $res[0][0] . $postf;
        RedirUrl($url);
    } elseif (sizeof($res) > 1) {
        ?><!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'><html> <head><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php
                        $musconfig = json_decode(file_get_contents("musconfig.json"), true);
                        echo($musconfig[6]);
                        ?></head>
            <body>
                <p>Several matching items in <?php echo( $mus); ?>.</p>
                <p>Select the suitable object, please:</p><ul>
                    <?php
                    foreach ($res as &$row) {
                        $url = "http://" . $pref . $row[0] . $postf;
                        ?>
                        <li><a href=' <?php echo( str_replace('&', '&amp;', $url)); ?> '>
                                <?php echo( $row[1]); ?>
                            </a></li>
                    <?php } ?>
                </ul>
                <p>Return to <a href='./mus.php'><?php echo($musconfig[7]); ?></a>&nbsp;|&nbsp;<a href='./mus.php?help=impressum'>Impressum</a></p></body></html> <?php
    }
    exit();
}

/** Redirects the user to a given URL. Supports browsers without javascript. 
 * $urlinput - url to redirect the user to */
function RedirUrl($urlinput) {
    ?><!DOCTYPE html><html><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'><meta name="viewport" content="width=device-width, initial-scale=1.0"><script type="text/javascript">window.location.href = "<?php echo($urlinput); ?>";</script><noscript>
            <meta http-equiv="Refresh" content="0; URL=<?php echo(str_replace(' ', '%20', str_replace('&', '&amp;', $urlinput))); ?>">
            </noscript><meta charset="utf-8"></head><body><p>&nbsp;<a href="./mus.php?help=impressum">Impressum</a></p></body></html><?php
    exit();
}

$found = false;
$musarray = json_decode(file_get_contents("museumdefinitions.json"), true); //Loads museum definitions
$musaliases = json_decode(file_get_contents("musaliases.json"), true); //Loads museum aliases
$helpmode = filter_input(INPUT_GET, 'help', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW); // Loads the help attribute used to display help (.../mus.php?help=help) and aliases (.../mus.php?help=aliases)
if ($helpmode == "aliases") {
    ?>
    <!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'><html> <head><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php
            $musconfig = json_decode(file_get_contents("musconfig.json"), true);
            echo($musconfig[6]);
            showcss();
            ?>
        </head> <body> <h2>The list of accepted aliases </h2><div class=limit>
                <table cellspacing="0" style="border-width:0px;border-collapse:collapse;" border =0 id="tab" class="tab">
                    <tr><td align='left'><b>Alias</b></td><td align='left'><b>Museum</b></td><td align='left'><b>Strict match required</b></td></tr><?php
                    $sortedarray = array();
                    foreach ($musaliases as &$musdef) {

                        $sortedarray[] = $musdef[0];
                    }
                    natcasesort($sortedarray);
                    foreach ($sortedarray as &$musdef) {
                        foreach ($musaliases as &$musalias) {
                            $match = strcasecmp($musdef, $musalias[0]) == 0;

                            if ($match == true) {
                                $bo = ($musalias[2]) ? 'true' : 'false';
                                ?><tr><td align='left'><?php echo($musalias[0]); ?></td><td align='left'><?php echo($musalias[1]); ?></td><td align='left'><?php echo($bo); ?></td></tr><?php
                                break;
                            }
                        }
                    }
                    ?>
                </table> <p>(When <i>Strict match required</i> is false, <?php echo($musconfig[7]); ?> recognises any string containing the alias as a museum name. Thus 'British Museum, London, is recognised as an alias for 'BM', as it contains the string 'British Museum'.)&nbsp;</p><p>
                    <a href='./mus.php'><?php echo($musconfig[7]); ?></a>&nbsp;|&nbsp;<a href='./mus.php?help=help'>About this page</a>&nbsp;|&nbsp;<a href='./mus.php?help=impressum'>Impressum</a></p></div></body></html>
    <?php
    exit();
} elseif ($helpmode == "help") {
    ?>
    <!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'><html> <head><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php
            $musconfig = json_decode(file_get_contents("musconfig.json"), true);
            echo($musconfig[6]);
            showcss();
            ?>
        </head> <body> <h2>About this tool</h2><div class=limit><p>The <?php echo($musconfig[7]); ?> is a PHP tool aimed to facilitate locating the descriptions and images of ancient Egyptian objects in online catalogues of major museums. 
                    Online catalogues (ranging from selections of highlights to complete digital inventories) are now offered by almost all major museums holding ancient Egyptian items and have become indispensable in research work. 
                    Yet the variety of web interfaces and of search rules may overstrain any person performing many searches in different online catalogues.</p>
                <p><?php echo($musconfig[7]); ?> was made to provide a single search point for finding objects by their inventory numbers in major collections of Egyptian antiquities that have online catalogues. 
                    It tries to convert user input into search queries recognised by museums’ websites. (Thus on museum websites, stela Geneva D 50 should be searched as “D 0050,” statue Vienna ÄS 5046 as “AE_INV_5046,” and coffin Turin Suppl. 5217 as “S. 05217.” <?php echo($musconfig[7]); ?> aims to allow searching for inventory numbers in the form, in which they are cited in scholarly literature.) The following online catalogues are supported:</p>
                <table cellspacing="0" style="border-width:0px;border-collapse:collapse;" border =0 id="tab" class="tab">
                    <tr><td align='left'><b>Short name</b></td><td align='left'><b>Full name</b></td><td align='left'><b>Free license</b></td></tr>
                    <?php
                    $sortedarray = array();
                    foreach ($musarray as &$musdef) {
                        if ($musdef[4] !== true) {
                            $sortedarray[] = $musdef[0];
                        }
                    }
                    natcasesort($sortedarray);
                    foreach ($sortedarray as &$musdef1) {
                        $musarray1 = json_decode(file_get_contents("museumdefinitions.json"), true);
                        foreach ($musarray1 as &$musfulldef) {
                            $match = strcasecmp($musdef1, $musfulldef[0]) == 0;
                            if ($match == true) {
                                ?>
                                <tr><td align='left'> <?php echo($musfulldef[0]); ?></td><td align='left'><?php echo str_replace('&', '&amp;', $musfulldef[7]); ?></td><td align='left'><?php echo ($musfulldef[8]); ?></td></tr><?php
                                break;
                            }
                        }
                    }
                    ?>
                </table> <div style="  border-left: solid #000000;  padding-left:15px;">
                    Note: NC licenses virtually only allow using images in presentations and theses. CC 0 and CC BY allow using images in printed publications and on websites.<p>Search forwarding is not yet supported for <a href='http://www.globalegyptianmuseum.org/advanced.aspx?lan=E'>Global Egyptian Museum</a> and <a href='http://www.bible-orient-museum.ch/bodo/'>Bible and Orient Museum, Fribourg</a>.</p><p>More information on Egyptian collections can be found online on <a href='http://www.trismegistos.org/coll/list_all.php'>Trismegistos</a>, <a href='http://egyptartefacts.griffith.ox.ac.uk/?q=destinations-index'>Artefacts of Excavation</a>, and <a href='http://www.desheret.org/museum.html'>Desheret.org</a>.</p> 
                </div><p>The tool can be used in two ways. First, one may use the online search interface. One may select the museum, enter the searched inventory number in the box, and press “Search.” Then the browser is redirected either to the object desription in the online museum catalogue or to a search results page on the museum website.
                    Second, one may send HTTP GET queries to the <?php echo($musconfig[7]); ?> in order to connect it to a one’s own online or offline application by creating query URLs of the following form:</p>
                <code>http://static.egyptology.ru/varia/mus.php?museum=(Museum)&amp;no=(Inventory number)</code>
                <p>In order to provide compatibility with other databases, which may use different designations of the museums, <?php echo($musconfig[7]); ?> supports <a href="./mus.php?help=aliases">a number of aliases</a>. Examples of query URLs:</p>
                <p><a href='./mus.php?museum=Leyden&amp;no=D%20127' rel='nofollow'><code>http://static.egyptology.ru/varia/mus.php?museum=Leyden&amp;no=D 127</code></a></p>
                <p><a href='./mus.php?museum=New%20York,%20Metropolitan%20Museum%20of%20Art&amp;no=56.136' rel='nofollow'><code>http://static.egyptology.ru/varia/mus.php?museum=New York, Metropolitan Museum of Art&amp;no=56.136</code></a></p>
                <p><a href='./mus.php?museum=Turin&amp;no=Cat.%201374' rel='nofollow'><code>http://static.egyptology.ru/varia/mus.php?museum=Turin&amp;no=Cat. 1374</code></a></p>
                <p>In case you wish to adapt <?php echo($musconfig[7]); ?> for the needs of a different discipline, you may make use of its <a href='https://github.com/ailintom/Egyptological-Museum-Search'>source code published on Github</a>.</p>
                <p>&nbsp;</p><p><a href='./mus.php'><?php echo($musconfig[7]); ?></a>&nbsp;|&nbsp;<a href='./mus.php?help=impressum'>Impressum</a></p></div></body></html>
    <?php
    exit();
} elseif ($helpmode == "impressum") { // Displays Impressum 
    $musconfig = json_decode(file_get_contents("musconfig.json"), true);
    ?>
    <!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'><html> <head><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php
            $musconfig = json_decode(file_get_contents("musconfig.json"), true);
            echo($musconfig[6]);
            showcss();
            ?>
        </head> <body> <div class=limit><p><h2>Impressum</h2><?php
                echo($musconfig[5]);
                ?><p>&nbsp;</p><p>Return to <a href='./mus.php'><?php echo($musconfig[7]); ?></a>.</p></div></body></html>
        <?php
        exit();
    }
// This is the beginning of the main procedure
    $mus = filter_input(INPUT_GET, 'museum', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW); // Gets the parameters of the GET query containing the museum name and the searched inventory number
    $accno = trim(filter_input(INPUT_GET, 'no', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW));
    $accno = trim(str_replace("&#34;", '', str_replace('"', '', $accno))); // removes quotation signs
    if (strcasecmp(substr($accno, 0, 4), 'inv.') == 0 or strcasecmp(substr($accno, 0, 4), 'inv ') == 0) { // removes the word inv. from the beginning of the searched string
        $accno = trim(substr($accno, 4));
    }
    if (strcasecmp(substr($accno, 0, 3), 'no.') == 0 or strcasecmp(substr($accno, 0, 3), 'no ') == 0) { // removes the word no. from the beginning of the searched string
        $accno = trim(substr($accno, 3));
    }
    $accno = trim(str_ireplace($mus, '', $accno)); // removes the name of the museum from the searched string in case the user has entered it twice
    foreach ($musaliases as &$musalias) { // matches the alias from the musaliases.json
        if ($musalias[2] === true) { /* $musalias[2] === true exact match required; $musalias[2] === false museum name should be contained */
            $match = strcasecmp($mus, $musalias[0]) == 0;
        } else {
            $match = stripos($mus, $musalias[0]) !== false;
        }
        if ($match == true) {
            $mus = $musalias[1];
            break;
        }
    }
    $accno = trim(str_ireplace($mus, '', $accno)); // removes the name of the museum from the searched string in case the user has entered it twice
    /*     * *********************** MMA  */
// MMA is processed here separately for in case of no match from JSON query, the standard MMA search page will be returned by the usual procedure
    if ($mus === 'MMA') {
        //This part of the code fetches the JSON file with search results from MMA and redirects the user to the results
        // If the JSON contains no results the basic MMA search procedure will be performed later
        $accno = preg_replace('/(\d)[-\s\/]+(?=\d)/', '$1.', $accno); //replace spaces and slashes between digits with spaces
        $accno = preg_replace('/(?<=\d)\s+(?=\D)/', '', $accno); //remove spaces between the number and the extension
        if (preg_match("/^\d\.\d.*/", $accno)) {
            $accno = "0" . $accno;
        }
        $url = "https://collectionapi.metmuseum.org/public/collection/v1/search?q=" . str_replace(" ", "%20", $accno);
        $MMAjson = json_decode(downloadmusjson($url), true);
        $webid = array_slice($MMAjson["objectIDs"], 0, 5);
        $mmaids = array();
// Test the results and filter those with inventory matching the searched value
        $cnt = 0;
        foreach ($webid as &$searchres) {
            $url = "https://collectionapi.metmuseum.org/public/collection/v1/objects/" . $searchres;
            $objectjson = json_decode(downloadmusjson($url), true);
            $cnt = $cnt + 1;
            if (($objectjson['accessionNumber'] == $accno) or preg_match("/" . preg_quote($accno) . "\D.*/", $objectjson['accessionNumber'])) {
                $mmaids[] = array($searchres, $objectjson['accessionNumber']);
            } elseif ($cnt > 0) {
                break;
            }
        }

        if (count($mmaids) > 0) {
            ReturnResultsFromArray($mmaids, "www.metmuseum.org/art/collection/search/", "", $mus);
        }
    } elseif ($mus === 'Berlin') {
        if (is_numeric(substr($accno, 0, 1))) {
            $accno = "ÄM " . $accno;
        }
    } elseif ($mus === 'Fitzwilliam') {
        $accno = str_replace(" ", ".", $accno);
        $url = "http://data.fitzmuseum.cam.ac.uk/api/?query=ObjectNumber:" . $accno;
        $Fitzjson = json_decode(downloadmusjson($url), true);
        if ($Fitzjson["total"] > 0) {
            $webid = $Fitzjson["results"];
            foreach ($webid as &$searchres) {
                if (($searchres['ObjectNumber'] == $accno) or preg_match("/" . preg_quote($accno) . "\D.*/", $searchres['ObjectNumber'])) {

                    $mmaids[] = array($searchres['priref'], $searchres['ObjectNumber']);
                }
            }
            if (count($mmaids) == 0) {
                foreach ($webid as &$searchres) {
                    $mmaids[] = array($searchres['priref'], $searchres['ObjectNumber']);
                }
            }
            if (count($mmaids) > 0) {
                ReturnResultsFromArray($mmaids, "data.fitzmuseum.cam.ac.uk/id/object/", "", $mus);
            }
        }
    }
//The procedure looks for the matching museum definition in $musarray
    foreach ($musarray as &$musdef) {
        if ($musdef[3] === true) {
            $match = strcasecmp($mus, $musdef[0]) == 0;
        } else {
            $match = stripos($mus, $musdef[0]) !== false;
        }
        if ($match == true) {
            $found = true;
            if ($musdef[5] == true) {
                /** if ($musdef[0] == 'OIM') { */
                /*                 * *********************** OIM  */
                /**   $accno = preg_replace('/(\d)[. ](?=\d)/', '$1', $accno);
                  $pos = firstnum($accno);
                  if (!($pos === false)) { // the procedure replaces the searched OIM number into "E" + the numerical part. This is Egyptology-specific. ("E" is for Egyptian collection in OIM).
                  $accno = "E" . substr($accno, $pos);
                  }
                  $url = "https://oisolr.uchicago.edu/solr/oidbcatalogue/search-museum-collection/?&q=SrchRegisNumber:(" . str_replace(" ", "%20", $accno) . ")&facet=true&facet.mincount=1&fq=&facet.sort=count&sort=score%20desc&rows=50&start=0&wt=json";
                  $OIMraw = trim(downloadmusjson($url));
                  if ((strpos($OIMraw, 'docs') === false) or ( strpos($OIMraw, '"numFound":0') !== false)) { // Nothing found or false result
                  $url = "http://" . $musdef[6];
                  RedirUrl($url);
                  exit();
                  }
                  $OIMjson = json_decode(substr($OIMraw, 4, -1), true);
                  $webid = $OIMjson["response"]["docs"];
                  $mmaids = array();
                  foreach ($webid as &$searchres) {
                  $mmaids[] = array($searchres['irn'], $searchres['RegistrationNumber'][0]);
                  }
                  if (count($mmaids) > 0) {
                  ReturnResultsFromArray($mmaids, $musdef[1], $musdef[2], $mus);
                  }
                  } else */
                /** if ($musdef[0] == 'BM') {
                  //*                 * ***********************BM
                  // In the following line the script loads JSON with search results for "EA" + the digits contained in the searched acc. no. This is Egyptology-specific. ("EA is for Egyptian collection in BM).
                  $acc = preg_replace("/[^0-9]/", "", $accno);
                  $bmdata = "PREFIX owl: <http://www.w3.org/2002/07/owl#> SELECT ?webidval WHERE { ?auth owl:sameAs <http://collection.britishmuseum.org/id/object/Y_EA" . $acc . "> .   ?auth owl:sameAs ?webidval .   FILTER (?webidval != <http://collection.britishmuseum.org/id/object/Y_EA" . $acc . ">) }";
                  $BMjson = json_decode(downloadmusjsonpost("https://collection.britishmuseum.org/sparql", $bmdata), true);

                  $webid = substr($BMjson["results"]["bindings"][0]["webidval"]["value"], 46);
                  if ($webid == null) {

                  $url = "https://" . $musdef[6] . 'EA' . preg_replace("/[^0-9]/", "", $accno);
                  RedirUrl($url);
                  exit();
                  }  //uncomment when SPARQL is working again
                  $url = "https://" . $musdef[1] . $webid . "&?museumno=" . $acc . $musdef[2];
                  RedirUrl($url);
                  } else */
                if ($musdef[0] == 'Leiden') {
                    /*                     * *********************** LEIDEN */
                    $accno = str_ireplace('Æ', 'AE', $accno);
                    if (preg_match("/^[^0-9 .][^0-9 .][^0-9 .]\d.*/", $accno)) {
                        $accno = substr($accno, 0, 2) . " " . substr($accno, 2);
                    }
                    if (preg_match("/^[^0-9 .][^0-9 .]\d.*/", $accno)) {
                        $accno = substr($accno, 0, 2) . " " . substr($accno, 2);
                    }
                    if (preg_match("/^[^0-9 .]\d.*/", $accno)) {
                        $accno = substr($accno, 0, 1) . " " . substr($accno, 1);
                    }
                    $accno = str_replace(' ', '.', $accno);
                    $accno = str_replace('..', '.', $accno);
                    $result = mySQLqueryex($musdef[0], "REPLACE(inv, ' ', '.') = ", $accno);
                    if ($result->num_rows === 0) {
                        if (strpos($accno, '/') !== false and substr($accno, 0, 1) !== "F" and substr($accno, 0, 1) !== "f") {

                            $result = mySQLqueryex($musdef[0], "REPLACE(inv, ' ', '.') = ", "F." . $accno);
                        }
                    }
                    if ($result->num_rows === 0) {

                        $result = mySQLqueryex($musdef[0], "REPLACE(inv, ' ', '.') like ", "$accno-%");
                    }
                    if ($result->num_rows === 0) {

                        $result = mySQLqueryex($musdef[0], "REPLACE(REPLACE(inv, ' ', ''),'.','') like ", str_replace('.', '', $accno));
                    }
                    if ($result->num_rows === 0) {
                        if (stripos($accno, 'bis') !== false) {
                            $result = mySQLqueryex($musdef[0], "REPLACE(inv, ' ', '.') like ", str_ireplace('bis', '%bis%', $accno));
                        }
                    }
                    // This is Egyptology-specific. (Leemans Numbers refer to a catalogue of the Egyptian collection).
                    if ($result->num_rows === 0) {
                        $result = mySQLqueryex('Leiden (Leemans)', "REPLACE(inv, ' ', '.') = ", $accno);
                    }
                    if ($result->num_rows === 0) {
                        $url = "https://" . $musdef[6] . $accno;
                        RedirUrl($url);
                        exit();
                    }
                    if ($result->num_rows > 1) {
                        ReturnResults($result, $musdef[1], $musdef[2], $mus);
                    }
                    $webid = $result->fetch_assoc();

                    $url = "https://" . $musdef[1] . $webid["webid"] . $musdef[2];
                    RedirUrl($url);
                } elseif ($musdef[0] == 'Jerusalem') {
                    /*                     * ***********************JERUSALEM */
                    /* $accno = str_replace(' ', '.', $accno); */
                    $accno = str_replace('..', '.', $accno);
                    $accno = str_replace('  ', ' ', $accno);
                    $result = mySQLqueryex($musdef[0], "inv = ", $accno);
                    if ($result->num_rows === 0) {
                        $result = mySQLqueryex($musdef[0], "inv like ", "%$accno%");
                    }
                    if ($result->num_rows === 0) {
                        $url = 'http://' . $musdef[6] . '%22' . $accno . '%22';
                        RedirUrl($url);
                        exit();
                    }
                    ReturnResults($result, $musdef[1], $musdef[2], $mus);
                } elseif ($musdef[0] == 'Bruxelles') {
                    /*                     * ***********************BRUXELLES */
                    $accno = preg_replace('/(\d)[. ](?=\d)/', '$1', $accno);
                    $pos = firstnum($accno);
                    if ($pos === false) {
                        $url = "http://" . $musdef[6] . $accno;
                        RedirUrl($url);
                    }
                    $accno = substr($accno, $pos);
                    $endpos = firstnonnum($accno);
                    if ($endpos === false) {
                        $num = $accno;
                        $nonnum = "";
                    } else {
                        $num = substr($accno, 0, $endpos);
                        $nonnum = substr($accno, $endpos);
                    }
                    $numstr = "E." . sprintf("%05d", $num); // This is Egyptology-specific. ("E" is for Egyptian collection in Bruxelles).

                    $result = mySQLqueryex($musdef[0], "inv =", $numstr . $nonnum);
                    if ($result->num_rows === 0) {
                        $result = mySQLqueryex($musdef[0], "inv LIKE", $numstr . $nonnum . "%");
                    }
                    if ($result->num_rows === 0) {
                        $nonnumrep = str_replace('.', '%', str_replace(' ', '%', $nonnum));
                        $result = mySQLqueryex($musdef[0], "inv LIKE", $numstr . $nonnumrep . "%");
                    }
                    if ($result->num_rows === 0) {
                        $result = mySQLqueryex($musdef[0], "inv LIKE", $numstr . "%");
                    }
                    if ($result->num_rows === 0) {
                        $url = "http://" . $musdef[6];
                        RedirUrl($url);
                        exit();
                    }
                    ReturnResults($result, $musdef[1], $musdef[2], $mus);
                } elseif ($musdef[0] == 'Edinburgh') {
                    $accno = str_replace('  ', ' ', str_replace(' ', '.', $accno));
                    if (is_numeric(substr($accno, 0, 1))) {
                        $accno = 'A.' . $accno; // This is Egyptology-specific. ("A" is used for all Egyptian [and possibly other] items in Edinburgh).
                    }
                    $queryURL = "https://www.nms.ac.uk/api/axiell?output=json&database=ChoiceCollect&search=(object_number=%27" . $accno . "%27)&limit=12&startfrom=1";
#echo ($queryURL);
                    $json = downloadmusjson($queryURL);
                    $obj = json_decode($json, true);
                    #print_r($obj);
                    $res = [];
                    if (isset($obj['adlibJSON']['recordList'])) {
                        $recordList = $obj['adlibJSON']['recordList'];

                        // Iterate over the recordList to get priref and object_number
                        foreach ($recordList as $record) {
                            if (isset($record['@priref'], $record['object_number'])) {
                                array_push($res, [$record['@priref'], $record['object_number']]);
                            }
                        }
                    }
                    ReturnResultsFromArray($res, "www.nms.ac.uk/search-our-collections/collection-search-results?entry=", "", "Edinburgh");
                } elseif ($musdef[0] == 'Torino') {
                    /*                     * ***********************TORINO */
                    $accno = preg_replace('/(\d)[. ](?=\d)/', '$1', $accno);
                    $pos = firstnum($accno);
                    if (!($pos === false)) {
                        $numaccno = substr($accno, $pos);
                        //  $pref = substr($accno, 0, $pos);
                        $endpos = firstnonnum($numaccno);
                        if (!($endpos === false)) {
                            $nonnum = str_replace(' ', '', substr($numaccno, $endpos));
                            $numaccno = substr($numaccno, 0, $endpos);
                        }
                    }

                    if (stripos($accno, 'S') !== false) {
                        $accno = "S. " . sprintf("%05d", $numaccno) . $nonnum;
                    }
                    if (stripos($accno, 'prov') !== false) {
                        $accno = "Provv. " . sprintf("%04d", $numaccno) . $nonnum;
                    }
                    if (stripos($accno, 'CG') !== false) {
                        $accno = "CGT " . sprintf("%05d", $numaccno) . $nonnum;
                    }
                    if (stripos($accno, 'C') !== false) {
                        $accno = "Cat. " . sprintf("%04d", $numaccno) . $nonnum;
                    }

                    $result = mySQLqueryex($musdef[0], 'inv =', $accno);
                    if ($result->num_rows === 0 and preg_match("/.*\/.*/", $accno)) {
                        if (preg_match("/^\d.*/", $accno)) {
                            $result = mySQLqueryex($musdef[0], 'inv like ', '%' . str_replace("/", "/%", $numaccno . $nonnum));
                        } else {
                            $result = mySQLqueryex($musdef[0], 'inv like ', str_replace("/", "/%", $accno));
                        }
                    }
                    if ($result->num_rows === 0) {

                        if (!$numaccno == 0) {

                            $result = mySQLqueryex($musdef[0], 'STRIP_NON_DIGIT(inv) =', $numaccno);
                        }
                        if ($result->num_rows === 0) {
                            $url = "http://" . $musdef[6];
                            RedirUrl($url);
                            exit();
                        }
                    }
                    ReturnResults($result, $musdef[1], $musdef[2], $mus);
                } else {
                    $url = "http://" . $musdef[1];
                    RedirUrl($url);
                    exit();
                }
            } else {
                /*                 * ***********************OTHER MUSEUMS */
                if (in_array($musdef[0], array("Stockholm", "Bibliotheca Alexandrina", "Bologna", "Glasgow Hunterian", "Lyon", "Madrid", "Manchester", "Swansea", "Warszawa"))) {
                    $protocol = "http";
                } else {
                    $protocol = "https";
                }

                switch ($musdef[0]) {
                    case 'Genève':
                        if (is_numeric($accno)) {
                            $accno = sprintf("%06d", $accno);
                        } elseif ((preg_match("/^\D\d.*/", $accno)) || (preg_match("/^\D.\d.*/", $accno))) {
                            $pos = firstnum($accno);
                            if (!($pos === false)) {
                                $num = substr($accno, $pos);

                                $endpos = firstnonnum($num);
                                if (!($endpos === false)) {
                                    $num = substr($num, 0, $endpos);
                                    $nonnum = substr($num, $endpos);
                                }
                                if (!($pos === false)) {
                                    $accno = substr($accno, 0, 1) . " " . sprintf("%04d", $num) . $nonnum;
                                }
                            }
                        }
                        break;
                    case 'Glasgow Hunterian':
                        $accno = mb_strtoupper(str_replace(' ', '.', $accno));
                        if (mb_substr($accno, 0, 6) != 'GLAHM:') {

                            if (preg_match("/^\D\d.*/", $accno)) {
                                $accno = mb_substr($accno, 0, 1) . "." . substr($accno, 1);
                            }
                            if (preg_match("/^\d.*/", $accno)) {
                                $accno = "D." . $accno;
                            }
                            $accno = 'GLAHM:' . $accno;
                        }
                        //D.1921.35

                        break;
                    case 'Lyon':
                        $accno = str_replace('.', ' ', $accno);
                        if (preg_match("/^\D\d.*/", $accno)) {
                            $accno = substr($accno, 0, 1) . " " . substr($accno, 1);
                        }
                        break;
                    case 'Allard Pierson':
                        $accno = preg_replace('~[^0-9]~', '', $accno);
                        if (is_numeric($accno)) {
                            $accno = sprintf("%05d", $accno);
                        }
                        $accno = "%252528U002B%252528SUPERDEFAULT%25253A%252528apm" . $accno . "%252529+DEFAULT%25253A%252528apm" . $accno . "%252529%252529%252529";
                        break;
                    case 'Walters':
                    case 'Boston':
                    case 'Brooklyn':
                        $accno = preg_replace('/(\d)[-\s\/]+(?=\d)/', '$1.', $accno); // remove spaces and slashes between numbers
                        if (preg_match("/^\d\d\d\D.*/", $accno) or preg_match("/^\d\d\d$/", $accno) or preg_match("/^\d\d\d\d\d\D.*/", $accno) or preg_match("/^\d\d\d\d\d$/", $accno) or preg_match("/^\d\d\d\d[^0-9.].*/", $accno) or preg_match("/^\d\d\d\d$/", $accno)) {
                            $accno = substr($accno, 0, 2) . "." . substr($accno, 2);
                        } elseif (preg_match("/^\d\d\d\d\d\d\D.*/", $accno) or preg_match("/^\d\d\d\d\d\d$/", $accno) or preg_match("/^\d\d\d\d\d\d\d\D.*/", $accno) or preg_match("/^\d\d\d\d\d\d\d$/", $accno)) {
                            if (substr($accno, 0, 2) == 19 or substr($accno, 0, 2) == 20) {
                                $accno = substr($accno, 0, 4) . "." . substr($accno, 3);
                            } else {
                                $accno = substr($accno, 0, 2) . "." . substr($accno, 2);
                            }
                        }
                        break;
                    case 'Torino':
                        /*                         * ***********************TORINO */
                        $accno = preg_replace('/(\d)[. ](?=\d)/', '$1', $accno);
                        $pos = firstnum($accno);
                        if (!($pos === false)) {
                            $numaccno = substr($accno, $pos);
                            //  $pref = substr($accno, 0, $pos);
                            $endpos = firstnonnum($numaccno);
                            $nonnum = "";
                            if (!($endpos === false)) {
                                $nonnum = str_replace(' ', '', substr($numaccno, $endpos));
                                $numaccno = substr($numaccno, 0, $endpos);
                            }
                        }

                        if (stripos($accno, 'S') !== false) {
                            $accno = "inventoryNumber=S. " . $numaccno . $nonnum;
                        } elseif (stripos($accno, 'prov') !== false) {
                            $accno = "inventoryNumber=Provv. " . $numaccno . $nonnum;
                        } elseif (stripos($accno, 'CG') !== false) {
                            $accno = "cgt=" . $numaccno . $nonnum;
                        } elseif (stripos($accno, 'C') !== false) {
                            $accno = "inventoryNumber=Cat. " . $numaccno . $nonnum;
                        } else {
                            $accno = "inventoryNumber=" . $accno;
                        }
                        break;
                    case 'Philadelphia':
                        $accno = preg_replace('~[ .]~', '', $accno);
                        break;
                    case 'Liverpool WM':
                        if (substr($accno, 0, 1) == "M") {
                            $accno = str_replace('.', '', str_replace(' ', '', $accno));
                        }
                        break;
                    case 'Moscow' :

                        if (is_numeric(substr($accno, 0, 1))) {
                            $accno = "I.1 " . $accno;
                        } else {
                            $accno = str_replace('a', 'а', str_replace('b', 'б', $accno));
                        }
                        break;

                    case 'Glasgow Burrell' :
                    case 'Glasgow Kelvingrove' :
                    case 'Field Museum' :
                        $pos = firstnum($accno);
                        $accno = substr($accno, $pos);
                        break;
                    case 'Stockholm' :
                        if (substr($accno, 0, 3) == "NME") {
                            $pos = firstnum($accno);
                            if (!($pos === false)) {
                                $accno = "NME " . sprintf("%03d", substr($accno, $pos));
                            }
                        } elseif (substr($accno, 0, 3) == "MME") {
                            $pos = firstnum($accno);
                            if (!($pos === false)) {
                                $stknum1 = substr($accno, $pos);

                                $endpos = firstnonnum($stknum1);
                                if (!($endpos === false)) {
                                    $num = substr($stknum1, 0, $endpos);
                                    $nonnum = substr($stknum1, $endpos);
                                    $pos = firstnum($nonnum);
                                    if (!($pos === false)) {
                                        $accno = "MME " . $num . ":" . sprintf("%03d", substr($nonnum, $pos));
                                    }
                                }
                            }
                        }
                        break;
                    case 'Bologna':
                    case 'UC':
                    case 'Wien': //remove all but the digits
                        $accno = preg_replace('~[^0-9]~', '', $accno);
                        break;
                    case 'Chicago ISAC':
                        if (preg_match("/e/i", substr($accno, 0, 1))) {
                            $pos = firstnum($accno);
                            if (!($pos === false)) {
                                $accno = "E" . substr($accno, $pos);
                            }
                        } elseif (preg_match("/\D/i", substr($accno, 0, 1))) {
                            $accno = preg_replace('~[^\w]~', '', $accno);
                        } else {
                            $accno = "E" . $accno;
                        }

                        break;
                    case 'Ny Carlsberg':
                        $accno = preg_replace('~[^0-9]~', '', $accno);
                        if (is_numeric($accno)) {
                            $accno = sprintf("%04d", $accno);
                        }
                        break;
                    case 'Durham':

                        $accno = preg_replace('~[ ]~', '', $accno);
                        if (!(substr($accno, 0, 5) == "DUROM")) {
                            $accno = preg_replace('~[.]~', '', $accno);
                        }
                        break;
                    case 'Swansea':
                        $accno = preg_replace('~[ .]~', '', $accno);
                        break;
                    case 'Washington':
                    case 'København':
                        $accno = str_replace(' ', '', $accno);
                        break;
                    case 'San Jose':
                        $accno = str_replace(' ', '-', $accno);
                        break;
                    case 'Bristol':
                        $accno = str_replace(' ', '', $accno);
                        if (is_numeric(substr($accno, 0, 1))) {
                            if (preg_match('#^(\d+)#', $accno, $match)) {  //Look for the digits
                                $numno = $match[1];    //Use the digits captured in the brackets from the RegEx match
                                if ($numno < 5231) {
                                    $accno = "H" . $accno;
                                } else {
                                    $accno = "Ha" . $accno;
                                }
                            }
                        }
                        break;
                    case "Edinburgh" :
                        /*                         * ***********************Edinburgh */
                        $accno = str_replace('  ', ' ', str_replace(' ', '.', $accno));
                        if (is_numeric(substr($accno, 0, 1))) {
                            $accno = 'A.' . $accno; // This is Egyptology-specific. ("A" is used for all Egyptian [and possibly other] items in Edinburgh).
                        }

                        break;
                    case 'Sydney':
                        $accno = str_replace(' ', '', $accno);
                        if (substr($accno, 0, 2) != "NM") {

                            $accno = "NM" . $accno;
                        }
                        break;
                    case 'Ashmolean':
                        $accno = str_replace(' ', '', $accno);
                        if (is_numeric(substr($accno, 0, 1))) {

                            $accno = "AN" . $accno;
                        }
                        break;
                    case 'Aberdeen':
                        $accno = str_replace(' ', '', $accno);
                        if (is_numeric(substr($accno, 0, 1))) {

                            $accno = "ABDUA:" . $accno;
                        }
                        break;
                    case 'BM':
                        $accno = str_replace(' ', '', $accno);
                        if (is_numeric(substr($accno, 0, 1))) {

                            $accno = "EA" . $accno;
                        }
                        break;
                }
                $url = $protocol . "://" . $musdef[1] . $accno . $musdef[2]; // This line forms the URL for all the museums supporting GET queries
                RedirUrl($url);
            }
        }
    }
// if an unknown museum name is supplied (or no museum name) the start page is displayed
    ?>
<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php
    $musconfig = json_decode(file_get_contents("musconfig.json"), true);
    echo($musconfig[6]);
    showcss();
    ?>
    </head> <body> <div class=limit><h2><?php echo($musconfig[7]); ?></h2><p>Select the museum and enter the inventory number </p></div><form action='mus.php' method='get'>
            <table cellspacing="0" style="border-width:0px;border-collapse:collapse;" border =0 id="tab" class="tab">
                <tr><td align='right'>Museum:</td><td align='left'><select name='museum' id='museum' style='max-width: 204px; min-width: 204px; width: 204px !important; height: 21px !important; min-height: 21px; border-style: solid; border-width: 1px; -ms-box-sizing:content-box; -moz-box-sizing:content-box; box-sizing:content-box; -webkit-box-sizing:content-box;'><?php
        $sortedarray = array();
        foreach ($musarray as &$musdef) {
            if ($musdef[4] !== true) {
                $sortedarray[] = $musdef[0];
            }
        }
        natcasesort($sortedarray);
        foreach ($sortedarray as &$musdef) {
        ?><option value='<?php echo ($musdef); ?>'><?php echo ($musdef); ?>  </option>    <?php } ?>
                        </select></td></tr><tr><td align='right'>Inventory number:</td><td align='left'><input type='text' name='no' id='no' style='max-width: 202px; min-width: 202px; width: 202px !important; height: 19px !important; min-height: 19px; border-style: solid; border-width: 1px; -ms-box-sizing:content-box; -moz-box-sizing:content-box; box-sizing:content-box; -webkit-box-sizing:content-box;' >
                    </td><tr><td align='right'>&nbsp;</td><td align='left'><input type='submit' value='Search'></td></tr></table> </form><p>&nbsp;</p><p>
            You can also try the <a href="javascript:void%20function(){function%20a(b,c){var%20d=document.createElement(%22a%22);if(d.style.display=%22none%22,g.appendChild(d),!0===c)d.target=%22_blank%22;else{var%20a=c%26%26c.exec(g.innerHTML)%3Fc.exec(g.innerHTML)[1].trim():%22download%22;d.setAttribute(%22download%22,a+%22.jpg%22)}d.href=b,d.click(),g.removeChild(d)}function%20c(b,c,d,e,f){var%20g=c,h=new%20Set(b.match(g));[...h].forEach(function(b,c){setTimeout(function(){var%20c=g.exec(b);g.lastIndex=0,a(d+c[1].replace(/%26amp;/g,%22%26%22)+e,f)},120*(c+1))})}var%20d,e=window.location.href,f=new%20XMLHttpRequest,g=document.body;if(/britishmuseum\.org/.test(e))d=e.replace(/.*\//gm,%22/api/_object%3Fid=%22),f.onreadystatechange=function(){if(4===f.readyState%26%26200===f.status%26%26f.responseText){var%20b=JSON.parse(f.responseText);b.hits.hits[0]._source.multimedia.forEach(function(b,c){setTimeout(function(){a(%22/api/_image-download%3Fid=%22+b.admin.id,/=%22object-detail__data-description%22%3E(.+%3F)%3C/)},120*(c+1))})}},f.open(%22GET%22,d,!0),f.send(null);else%20if(/brooklynmuseum\.org/.test(e))c(g.innerHTML,/data-full-img-url=%22(.*)%22/gm,%22%22,%22%22,!0);else%20if(/mfa\.org/.test(e)){var%20b=/(\/internal.*%3F3Aformat%253D)postage/gm;b.test(g.innerHTML)%3Fc(g.innerHTML,b,%22%22,%22full%22,/Accession%20Number%3C\/span%3E%3Cspan%20class=%22detailFieldValue%22%3E(.+%3F)%3C/):a(document.getElementsByName(%22og:image%22)[0].getAttribute(%22content%22),/Accession%20Number%3C\/span%3E%3Cspan%20class=%22detailFieldValue%22%3E(.+%3F)%3C/)}else%20if(/louvre\.fr/.test(e))c(g.innerHTML,/data-api-dl=%22(.*%3F)%22/gm,%22%22,%22%22,/Num%C3%A9ro%20principal\s+%3F:\s+%3F%3C\/span%3E(.+%3F)%3C/);else%20if(/rmo\.nl/.test(e))c(g.innerHTML,/(\/imageproxy\/jpg\/.*%3F)%22/gm,%22%22,%22%22,/Inventarisnummer:(.+%3F)%3C/);else%20if(/collections\.ucl\.ac\.uk/.test(e))c(g.innerHTML,/src=%22(.*%3F)%26amp;width/gm,%22%22,%22%22,/Number%3C\/div%3E%3Cdiv%20class=%22value%22%3ELDUCE-(UC.+%3F)%3C/);else%20if(/metmuseum\.org/.test(e))c(g.innerHTML,/data-superjumboimage=%22(.*%3F)%22/gm,%22%22,%22%22,!0);else%20if(/museoegizio\.it/.test(e))c(g.innerHTML,/Download%20%3Ca%20href=%22(.*%3F)%22/gm,%22%22,%22%22,/col-lg-9%22%3E\s*%3F%3Cspan%20class=%22value%22%3E(.+)%3C/);else%20if(/isac-idb\.uchicago\.edu/.test(e))c(g.innerHTML,/cycle-slideshow-image-container%22%20href=%22(.*%3F)%22/gm,%22%22,%22%22,!0);else%20if(/carmentis\.kmkg-mrah\.be/.test(e)){var%20b=/href=%22(.*%3F)%22%20data-fancybox=%22images/gm,h=document.getElementById(%22referenceTab-03%22);h%26%26h.classList.contains(%22referenceTabItem%22)%3F(d=h.getElementsByTagName(%22A%22)[0].getAttribute(%22href%22),f.onreadystatechange=function(){4===f.readyState%26%26200===f.status%26%26f.responseText%26%26c(f.responseText,b,%22%22,%22%22,/inv=(.+%3F)%26/)},f.open(%22GET%22,d,!0),f.send(null)):c(g.innerHTML,b,%22%22,%22%22,/inv=(.+%3F)%26/)}else%20/harbour\.man\.ac\.uk/.test(e)%3Fc(g.innerHTML,/\%3Firn=(\d*)/gm,%22/emuweb/objects/common/webmedia.php%3Firn=%22,%22%22,0):/bible-orient-museum\.ch/.test(e)%3Fc(g.innerHTML,/background-image:%20url\((.*%3F)\)/gm,%22%22,%22%22,0):/nms.ac\.uk/.test(e)%26%26c(g.innerHTML,/data-zoom-image=%22(.*%3F)%22/gm,%22%22,%22%22,/Museum%20reference%3C\/h3%3E%3Cp%3E(.+%3F)%3C/)}();">
                Museum Downloader</a> bookmarklet (version 1.5) to facilitate downloading images from online catalogues.
            <br> <a href="#popup1" tabindex="-1">Brief introduction to the bookmarklet.</a>

        <div id="popup1" class="overlay">
            <div class="popup">
                <a class="close" href="#" tabindex="-1" >&times;</a>
                <div class="content">
                    <h2>Egyptological Museum Downloader Bookmarklet</h2>

                    version 1.5 (16 July 2023)
                    <br>
                    The aim of this bookmarklet is to facilitate downloading images of Egyptian 
                    objects from the websites of major museums. 
                    <br>
                    Usage:  <ol>
                        <li>Click on the 'Museum Downloader' link on the main page and drag it to your browser’s bookmarks bar
                            (also called 'favorites bar' in some browsers).</li>
                        <li>Open an object page of an online museum catalogue, which contains images.</li>
                        <li>Press the link 'Museum Downloader' on your bookmarks bar.</li>
                        <li>Depending on the museum, the tool will attempt to either download all available images
                            or open all images in new tabs. Your browser may ask if you allow the website to download multiple files 
                            or to open multiple pop ups. If you do not allow, the bookmarklet does not 
                            work. However, you only have to allow once, for each museum.</li>
                        <li>Look in your downloads folder.</li> </ol>


                    The bookmarklet downloads images from the British Museum, Boston Museum 
                    of Fine Arts, Louvre, National Museum of Antiquities (Leiden), Petrie 
                    Museum, Egyptian Museum (Turin), Royal Museums of Art and History 
                    (Brussels), Manchester Museum, National Museums Scotland (Edinburgh), and Bible and Orient Museum (Fribourg).
                    <br> 
                    It opens all images in new tabs for the Brooklyn Museum, Metropolitan Museum, and 
                    Institute for the Study of Ancient Cultures Museum (Chicago).<br>
                    <br> The bookmarklet was tested in Firefox, Chrome, and Edge under Microsoft Windows. It does not work in Internet Explorer.<br>
                    I should like to thank Tobias Konrad for reporting a bug in an earlier version of this bookmarklet.
                </div>
            </div>
        </div>
    </p><p><a href='./mus.php?help=help'>List of online museum catalogues and information about this tool</a>&nbsp;|&nbsp;<a href='./mus.php?help=impressum'>Impressum</a></p></body></html>
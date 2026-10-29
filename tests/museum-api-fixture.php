<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

foreach (array('CURLOPT_SSL_VERIFYPEER', 'CURLOPT_RETURNTRANSFER', 'CURLOPT_URL', 'CURLOPT_USERAGENT', 'CURLOPT_HTTPHEADER', 'CURLOPT_POST', 'CURLOPT_POSTFIELDS') as $index => $constant) {
    if (!defined($constant)) {
        define($constant, $index + 1);
    }
}

function curl_init() {
    return new stdClass();
}

function curl_setopt($handle, $option, $value) {
    if ($option === CURLOPT_URL) {
        $handle->url = $value;
    }
    return true;
}

function curl_exec($handle) {
    $number = $_GET['no'] ?? '';
    if ($number === 'ERROR') {
        return false;
    }
    if ($number === 'INVALID') {
        return '{invalid';
    }
    if (strpos($handle->url, 'collection/v1.1/search?') !== false) {
        parse_str(parse_url($handle->url, PHP_URL_QUERY), $query);
        $normalized = array('35-3-110' => '35.3.110', '5.3.110' => '05.3.110');
        $expected = $normalized[$number] ?? $number;
        if (($query['q'] ?? null) !== $expected || ($query['isAccessionNumber'] ?? null) !== 'true' ||
                ($query['limit'] ?? null) !== '5') {
            throw new RuntimeException('Unexpected MMA search parameters');
        }
        if ($number === 'ZERO' || $number === 'A/1&B') {
            return '{"total":0,"objectIDs":null}';
        }
        return '{"total":2,"objectIDs":[101,102]}';
    }
    if (preg_match('~/collection/v1/objects/(\d+)$~', $handle->url, $match)) {
        if ($number === 'BADOBJECT') {
            return '{invalid';
        }
        if ($number === 'MISMATCH') {
            $accession = $match[1] === '101' ? 'OTHER' : 'MISMATCH';
        } elseif ($number === 'MULTI') {
            $accession = $match[1] === '101' ? 'MULTI' : 'MULTIa';
        } else {
            $normalized = array('35-3-110' => '35.3.110', '5.3.110' => '05.3.110');
            $accession = $match[1] === '101' ? ($normalized[$number] ?? $number) : 'OTHER';
        }
        return json_encode(array('accessionNumber' => $accession));
    }
    if (strpos($handle->url, 'data.fitzmuseum.cam.ac.uk/api/') !== false) {
        return '{"total":1,"results":[{"priref":456,"ObjectNumber":"OTHER"}]}';
    }
    if (strpos($handle->url, 'www.nms.ac.uk/api/') !== false) {
        return '{"adlibJSON":{"recordList":[{"@priref":"789","object_number":"A.1"}]}}';
    }
    throw new RuntimeException('Unexpected museum API request: ' . $handle->url);
}

function curl_error($handle) {
    return 'Simulated network failure';
}

if (!extension_loaded('mysqli')) {
    define('MYSQLI_REPORT_OFF', 0);
    define('MYSQLI_NUM', 2);

    function mysqli_report($flags) {
        $GLOBALS['mysqli_report_flags'] = $flags;
        return true;
    }

    class mysqli {
        public $connect_errno = 0;
        public $connect_error = '';
        public $errno = 0;
        public $error = '';

        public function __construct($host, $user, $password, $database) {
            if (($GLOBALS['mysqli_report_flags'] ?? null) !== MYSQLI_REPORT_OFF) {
                throw new RuntimeException('MySQLi strict reporting was not disabled');
            }
            if (($_GET['no'] ?? '') === 'DBFAIL') {
                $this->connect_errno = 1;
                $this->connect_error = 'Simulated connection failure';
            }
        }

        public function real_escape_string($value) {
            return addslashes($value);
        }

        public function query($sql) {
            if (($_GET['no'] ?? '') === 'QUERYFAIL') {
                $this->errno = 1;
                $this->error = 'Simulated query failure';
                return false;
            }
            return new MuseumFixtureResult();
        }

        public function close() {
            return true;
        }
    }

    class MuseumFixtureResult {
        public $num_rows = 1;

        public function fetch_assoc() {
            return array('webid' => '42', 'inv' => 'D.127');
        }
    }
}

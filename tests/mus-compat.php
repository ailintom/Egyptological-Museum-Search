<?php

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) {
    throw new RuntimeException("Cannot allocate test port: $error");
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$logFile = tempnam(sys_get_temp_dir(), 'mus-test-');
if ($logFile === false) {
    throw new RuntimeException('Cannot create test server log');
}

$command = array(
    PHP_BINARY,
    '-n',
    '-d', 'error_reporting=-1',
    '-d', 'display_errors=0',
    '-d', 'log_errors=1',
    '-d', 'auto_prepend_file=' . __DIR__ . DIRECTORY_SEPARATOR . 'museum-api-fixture.php',
    '-d', 'disable_functions=curl_init,curl_setopt,curl_exec,curl_error',
    '-S', $address,
    '-t', $root
);
$process = proc_open($command, array(
    0 => array('pipe', 'r'),
    1 => array('file', $logFile, 'a'),
    2 => array('file', $logFile, 'a')
), $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start PHP test server');
}
fclose($pipes[0]);

$success = false;
try {
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection !== false) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(50000);
    }
    if (!$ready) {
        throw new RuntimeException('PHP test server did not start');
    }

    $tests = array(
        array('', 200, 'Select the museum'),
        array('?help=help', 200, 'About this tool'),
        array('?help=aliases', 200, 'The list of accepted aliases'),
        array('?help=impressum', 200, 'Impressum'),
        array('?museum=BM&no=123', 200, 'EA123'),
        array('?museum=British%20Museum&no=123', 200, 'EA123'),
        array('?museum=BM', 200, 'britishmuseum.org'),
        array('?museum=unknown&no=1', 200, 'Select the museum'),
        array('?museum=Gen%C3%A8ve&no=D50a', 200, 'D 0050a'),
        array('?museum=Berlin&no=1634', 200, 'window.location.href = "https://search.smb.museum/?q=%C3%84M+1634&sammlungen=Ägyptisches+Museum+und+Papyrussammlung&has_images=false";'),
        array('?museum=Torino&no=Cat.', 200, 'inventoryNumber=Cat.'),
        array('?museum=MMA&no=35.3.110', 200, 'window.location.href = "https://www.metmuseum.org/art/collection/search/101";'),
        array('?museum=Met&no=35-3-110', 200, 'window.location.href = "https://www.metmuseum.org/art/collection/search/101";'),
        array('?museum=MMA&no=5.3.110', 200, 'window.location.href = "https://www.metmuseum.org/art/collection/search/101";'),
        array('?museum=MMA&no=MISMATCH', 200, 'window.location.href = "https://www.metmuseum.org/art/collection/search/102";'),
        array('?museum=MMA&no=MULTI', 200, "href=' https://www.metmuseum.org/art/collection/search/102 '"),
        array('?museum=MMA&no=ZERO', 200, 'window.location.href = "https://www.metmuseum.org/de/art/collection/search?searchField=AccessionNum&q=ZERO";'),
        array('?museum=MMA&no=INVALID', 200, 'window.location.href = "https://www.metmuseum.org/de/art/collection/search?searchField=AccessionNum&q=INVALID";'),
        array('?museum=MMA&no=ERROR', 200, 'window.location.href = "https://www.metmuseum.org/de/art/collection/search?searchField=AccessionNum&q=ERROR";'),
        array('?museum=MMA&no=BADOBJECT', 200, 'window.location.href = "https://www.metmuseum.org/de/art/collection/search?searchField=AccessionNum&q=BADOBJECT";'),
        array('?museum=MMA&no=A%2F1%26B', 200, 'window.location.href = "https://www.metmuseum.org/de/art/collection/search?searchField=AccessionNum&q=A%2F1%26B";'),
        array('?museum=MMA', 200, 'window.location.href = "https://www.metmuseum.org/de/art/collection/search?searchField=AccessionNum&q=";'),
        array('?museum=M%C3%BCnchen&no=%C3%84S%204313', 200, 'window.location.href = "https://sammlung.smaek.de/de/objects?offset=0&sort=field=sortInv%26order=asc%26type=text&view=masonry&f=search=%25C3%2584S%25204313";'),
        array('?museum=M%C3%BCnchen&no=A%26B%2F1', 200, 'window.location.href = "https://sammlung.smaek.de/de/objects?offset=0&sort=field=sortInv%26order=asc%26type=text&view=masonry&f=search=A%2526B%252F1";'),
        array('?museum=Fitzwilliam&no=A%2F1', 200, 'data.fitzmuseum.cam.ac.uk/id/object/456'),
        array('?museum=Fitzwilliam&no=ERROR', 200, 'data.fitzmuseum.cam.ac.uk/search/results?query=ERROR'),
        array('?museum=Edinburgh&no=1', 200, 'www.nms.ac.uk/search-our-collections/collection-search-results?entry=789'),
        array('?museum=Leiden&no=D127', 200, '42'),
        array('?museum=Leiden&no=DBFAIL', 200, 'Simulated connection failure'),
        array('?museum=Leiden&no=QUERYFAIL', 200, 'Simulated query failure'),
        array('?museum%5B%5D=BM', 400, 'Invalid query parameter: museum'),
        array('?no%5B%5D=1', 400, 'Invalid query parameter: no'),
        array('?help%5B%5D=help', 400, 'Invalid query parameter: help')
    );
    $context = stream_context_create(array('http' => array('ignore_errors' => true, 'timeout' => 10)));
    foreach ($tests as $test) {
        $response = fopen('http://' . $address . '/mus.php' . $test[0], 'r', false, $context);
        $headers = stream_get_meta_data($response)['wrapper_data'];
        $body = stream_get_contents($response);
        fclose($response);
        if ($body === false || !preg_match('/^HTTP\/\S+ (\d+)/', $headers[0], $match) ||
                (int) $match[1] !== $test[1] || strpos($body, $test[2]) === false) {
            throw new RuntimeException('Failed request ' . $test[0] . ': ' . ($headers[0] ?? 'no response'));
        }
    }
    $success = true;
    echo count($tests) . " compatibility checks passed\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    if (!$success) {
        fwrite(STDERR, file_get_contents($logFile));
    }
    unlink($logFile);
}

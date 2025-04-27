<?php
// error_handler.php
function handleErrors($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit();
}

set_error_handler("handleErrors");

function handleExceptions($exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit();
}

set_exception_handler("handleExceptions");
?>
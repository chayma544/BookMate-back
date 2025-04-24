<?php
error_log("Router accessed! URI: ".$_SERVER['REQUEST_URI']);
if (file_exists(__DIR__.'/books.php')) {
    error_log("Book.php exists!");
    require __DIR__.'/book.php';
} else {
    error_log("Book.php NOT FOUND at: ".__DIR__.'/book.php');
    http_response_code(404);
    echo "Endpoint not found";
}
?>
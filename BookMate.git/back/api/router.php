<?php
error_log("Router accessed! URI: " . $_SERVER['REQUEST_URI']);

// Extract the endpoint from the URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$endpoint = basename($uri); // Get the last part of the URI (e.g., "books.php" or "requests.php")

// Build the file path
$filePath = __DIR__ . '/' . $endpoint;

// Check if the file exists and include it
if (file_exists($filePath)) {
    error_log("$endpoint exists! Including $filePath");
    require $filePath;
} else {
    error_log("$endpoint NOT FOUND at: $filePath");
    http_response_code(404);
    echo json_encode(["error" => "Endpoint not found"]);
}
?>
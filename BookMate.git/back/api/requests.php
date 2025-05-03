<?php
// Request Management API
// This endpoint handles CRUD operations for book requests

// Headers for REST API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database connection
require_once __DIR__ . '../config/db.php'; 

// Start session (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

// Get current user ID from session
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Response utility function
function sendResponse($status, $message, $data = null) {
    header("HTTP/1.1 $status");
    $response = [
        "status" => $status,
        "message" => $message
    ];
    if ($data !== null) {
        $response["data"] = $data;
    }
    echo json_encode($response);
    exit;
}

// Get database connection from your existing db.php file
// Assuming your db.php file creates a $conn variable or similar
// If your connection variable has a different name, update this accordingly

// Get request method
$requestMethod = $_SERVER["REQUEST_METHOD"];

// Basic endpoint structure based on request method
switch ($requestMethod) {
    case 'GET':
        handleGetRequest($conn);
        break;
    case 'POST':
        handlePostRequest($conn);
        break;
    case 'PUT':
        handlePutRequest($conn);
        break;
    case 'DELETE':
        handleDeleteRequest($conn);
        break;
    default:
        sendResponse(405, "Method not allowed");
}

/**
 * Handle GET requests with different filtering options
 */
function handleGetRequest($conn) {
    // Ensure user is authenticated
    if (!isAuthenticated()) {
        sendResponse(401, "Unauthorized - Please log in");
    }

    // Extract query parameters
    $request_id = $_GET['request_id'] ?? null;
    $requester_id = $_GET['requester_id'] ?? null;
    $owner_id = $_GET['owner_id'] ?? null;

    // Prepare base query
    $baseQuery = "SELECT r.*, b.title, b.author_name, u1.FirstName as requester_name, u2.FirstName as owner_name 
                 FROM requests r
                 JOIN livre b ON r.book_id = b.book_id
                 JOIN user u1 ON r.requester_id = u1.user_id
                 JOIN user u2 ON r.owner_id = u2.user_id";
    
    // Apply filters based on provided parameters
    if ($request_id) {
        // Get 1: based on request_id
        $query = $baseQuery . " WHERE r.request_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $request_id);
    } else if ($requester_id) {
        // Get 2: based on requester_id
        $query = $baseQuery . " WHERE r.requester_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $requester_id);
    } else if ($owner_id) {
        // Get 3: based on owner_id
        $query = $baseQuery . " WHERE r.owner_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $owner_id);
    } else {
        // No filter specified
        sendResponse(400, "Missing filter parameter. Please specify request_id, requester_id, or owner_id");
    }

    // Execute query
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        sendResponse(200, "Requests retrieved successfully", $requests);
    } else {
        sendResponse(404, "No requests found with the specified criteria");
    }
}

/**
 * Handle POST requests to create a new request
 */
function handlePostRequest($conn) {
    // Ensure user is authenticated
    if (!isAuthenticated()) {
        sendResponse(401, "Unauthorized - Please log in");
    }

    // Get current user as requester
    $requester_id = getCurrentUserId();
    if (!$requester_id) {
        sendResponse(400, "Invalid requester ID");
    }

    // Parse request body
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (!isset($data['book_id']) || !isset($data['type'])) {
        sendResponse(400, "Missing required fields (book_id, type)");
    }

    // Extract data
    $book_id = $data['book_id'];
    $type = strtoupper($data['type']);
    $durée = $data['durée'] ?? null;
    $reasonText = $data['reasonText'] ?? null;
    $datedeb = $data['datedeb'] ?? date('Y-m-d');

    // Validate book exists and is available
    $bookQuery = "SELECT b.*, u.user_id as owner_id FROM livre b 
                 LEFT JOIN user u ON b.user_id = u.user_id
                 WHERE b.book_id = ? AND b.availability = 1";
    $stmt = $conn->prepare($bookQuery);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(404, "Book not found or not available");
    }
    
    $book = $result->fetch_assoc();
    $owner_id = $book['owner_id'];
    
    // Prevent requesting own book
    if ($requester_id == $owner_id) {
        sendResponse(400, "You cannot request your own book");
    }
    
    // Validate request type
    if ($type != 'BORROW' && $type != 'EXCHANGE') {
        sendResponse(400, "Invalid request type. Must be 'BORROW' or 'EXCHANGE'");
    }

    // Check if already requested
    $checkQuery = "SELECT * FROM requests WHERE requester_id = ? AND book_id = ? AND status = 'PENDING'";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $requester_id, $book_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(409, "You already have a pending request for this book");
    }

    // Create new request
    $insertQuery = "INSERT INTO requests (requester_id, book_id, type, status, datedeb, durée, reasonText, owner_id) 
                   VALUES (?, ?, ?, 'PENDING', ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("iissisi", $requester_id, $book_id, $type, $datedeb, $durée, $reasonText, $owner_id);
    
    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        sendResponse(201, "Request created successfully", ["request_id" => $request_id]);
    } else {
        sendResponse(500, "Failed to create request: " . $conn->error);
    }
}

/**
 * Handle PUT requests to update an existing request
 */
function handlePutRequest($conn) {
    // Ensure user is authenticated
    if (!isAuthenticated()) {
        sendResponse(401, "Unauthorized - Please log in");
    }

    // Parse request body
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (!isset($data['request_id'])) {
        sendResponse(400, "Missing required field: request_id");
    }
    
    $request_id = $data['request_id'];
    $current_user_id = getCurrentUserId();
    
    // Get the current request to check permissions
    $query = "SELECT * FROM requests WHERE request_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(404, "Request not found");
    }
    
    $request = $result->fetch_assoc();
    
    // Determine user's role in this request
    $is_requester = ($request['requester_id'] == $current_user_id);
    $is_owner = ($request['owner_id'] == $current_user_id);
    
    if (!$is_requester && !$is_owner) {
        sendResponse(403, "You don't have permission to modify this request");
    }
    
    // Build update query based on provided fields
    $updateFields = [];
    $params = [];
    $types = "";
    
    // Fields that can be updated by the requester
    if ($is_requester) {
        if (isset($data['type'])) {
            $updateFields[] = "type = ?";
            $params[] = strtoupper($data['type']);
            $types .= "s";
        }
        if (isset($data['durée'])) {
            $updateFields[] = "durée = ?";
            $params[] = $data['durée'];
            $types .= "i";
        }
        if (isset($data['reasonText'])) {
            $updateFields[] = "reasonText = ?";
            $params[] = $data['reasonText'];
            $types .= "s";
        }
        if (isset($data['datedeb'])) {
            $updateFields[] = "datedeb = ?";
            $params[] = $data['datedeb'];
            $types .= "s";
        }
    }
    
    // Status can be updated by the owner (accepting/rejecting)
    if ($is_owner && isset($data['status'])) {
        $status = strtoupper($data['status']);
        
        // Validate status
        if ($status != 'ACCEPTED' && $status != 'REJECTED') {
            sendResponse(400, "Invalid status. Must be 'ACCEPTED' or 'REJECTED'");
        }
        
        $updateFields[] = "status = ?";
        $params[] = $status;
        $types .= "s";
        
        // If accepted, update book availability
        if ($status == 'ACCEPTED') {
            $bookUpdateQuery = "UPDATE livre SET availability = 0 WHERE book_id = ?";
            $bookStmt = $conn->prepare($bookUpdateQuery);
            $bookStmt->bind_param("i", $request['book_id']);
            $bookStmt->execute();
        }
    }
    
    // If no valid fields to update
    if (empty($updateFields)) {
        sendResponse(400, "No valid fields to update");
    }
    
    // Prepare and execute update
    $updateQuery = "UPDATE requests SET " . implode(", ", $updateFields) . " WHERE request_id = ?";
    $types .= "i";
    $params[] = $request_id;
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        sendResponse(200, "Request updated successfully");
    } else {
        sendResponse(500, "Failed to update request: " . $conn->error);
    }
}

/**
 * Handle DELETE requests to cancel a request
 */
function handleDeleteRequest($conn) {
    // Ensure user is authenticated
    if (!isAuthenticated()) {
        sendResponse(401, "Unauthorized - Please log in");
    }
    
    // Extract request ID
    if (!isset($_GET['request_id'])) {
        sendResponse(400, "Missing request_id parameter");
    }
    
    $request_id = $_GET['request_id'];
    $current_user_id = getCurrentUserId();
    
    // Get the current request to check permissions
    $query = "SELECT * FROM requests WHERE request_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(404, "Request not found");
    }
    
    $request = $result->fetch_assoc();
    
    // Check permissions (only requester can cancel)
    if ($request['requester_id'] != $current_user_id) {
        sendResponse(403, "Only the requester can cancel the request");
    }
    
    // Can only cancel if status is PENDING
    if ($request['status'] != 'PENDING') {
        sendResponse(400, "Cannot cancel a request that is already " . $request['status']);
    }
    
    // Delete the request
    $deleteQuery = "DELETE FROM requests WHERE request_id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $request_id);
    
    if ($stmt->execute()) {
        sendResponse(200, "Request cancelled successfully");
    } else {
        sendResponse(500, "Failed to cancel request: " . $conn->error);
    }
}
?>
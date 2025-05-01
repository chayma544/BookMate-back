<?php
header("Access-Control-Allow-Origin: *"); 
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please log in']);
    exit();
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? 'user';

try {
    switch ($method) {
        case 'GET':
            // Get single request by ID
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("
                    SELECT 
                        r.*,
                        l.*,
                        l.user_id as book_owner_id,
                        r.requester_id,
                        owner.user_id as owner_id,
                        owner.FirstName as owner_first_name,
                        owner.LastName as owner_last_name,
                        owner.email as owner_email,
                        owner.profile_image as owner_profile_image,
                        requester.user_id as requester_id,
                        requester.FirstName as requester_first_name,
                        requester.LastName as requester_last_name,
                        requester.email as requester_email,
                        requester.profile_image as requester_profile_image
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user owner ON l.user_id = owner.user_id
                    JOIN user requester ON r.requester_id = requester.user_id
                    WHERE r.request_id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request) {
                    // Check permissions
                    if ($current_user_role !== 'admin' && 
                        $current_user_id != $request['requester_id'] && 
                        $current_user_id != $request['book_owner_id']) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Forbidden - You can only view your own requests']);
                        break;
                    }
                    
                    // Add image URLs
                    if (!empty($request['image_path'])) {
                        $request['book_image_url'] = "/api/books?image=" . basename($request['image_path']);
                    }
                    if (!empty($request['owner_profile_image'])) {
                        $request['owner_profile_image_url'] = "/api/users?image=" . basename($request['owner_profile_image']);
                    }
                    if (!empty($request['requester_profile_image'])) {
                        $request['requester_profile_image_url'] = "/api/users?image=" . basename($request['requester_profile_image']);
                    }
                    
                    echo json_encode($request);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Request not found']);
                }
            }
            // Get requests by user ID (as requester)
            elseif (isset($_GET['user_id'])) {
                if ($current_user_role !== 'admin' && $current_user_id != $_GET['user_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Forbidden - You can only view your own requests']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    SELECT 
                        r.*,
                        l.*,
                        owner.user_id as owner_id,
                        owner.FirstName as owner_first_name,
                        owner.LastName as owner_last_name,
                        owner.profile_image as owner_profile_image
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user owner ON l.user_id = owner.user_id
                    WHERE r.requester_id = ?
                ");
                $stmt->execute([$_GET['user_id']]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add image URLs
                foreach ($requests as &$request) {
                    if (!empty($request['image_path'])) {
                        $request['book_image_url'] = "/api/books?image=" . basename($request['image_path']);
                    }
                    if (!empty($request['owner_profile_image'])) {
                        $request['owner_profile_image_url'] = "/api/users?image=" . basename($request['owner_profile_image']);
                    }
                }
                
                echo json_encode($requests);
            }
            // Get requests for books owned by a specific user
            elseif (isset($_GET['owner_id'])) {
                if ($current_user_role !== 'admin' && $current_user_id != $_GET['owner_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Forbidden - You can only view requests for your own books']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    SELECT 
                        r.*,
                        l.*,
                        requester.user_id as requester_id,
                        requester.FirstName as requester_first_name,
                        requester.LastName as requester_last_name,
                        requester.profile_image as requester_profile_image
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user requester ON r.requester_id = requester.user_id
                    WHERE l.user_id = ?
                ");
                $stmt->execute([$_GET['owner_id']]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add image URLs
                foreach ($requests as &$request) {
                    if (!empty($request['image_path'])) {
                        $request['book_image_url'] = "/api/books?image=" . basename($request['image_path']);
                    }
                    if (!empty($request['requester_profile_image'])) {
                        $request['requester_profile_image_url'] = "/api/users?image=" . basename($request['requester_profile_image']);
                    }
                }
                
                echo json_encode($requests);
            }
            // Get all requests (admin only)
            else {
                if ($current_user_role !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Forbidden - Admin access required']);
                    break;
                }
                
                $stmt = $pdo->query("
                    SELECT 
                        r.*,
                        l.*,
                        owner.user_id as owner_id,
                        owner.FirstName as owner_first_name,
                        owner.LastName as owner_last_name,
                        owner.profile_image as owner_profile_image,
                        requester.user_id as requester_id,
                        requester.FirstName as requester_first_name,
                        requester.LastName as requester_last_name,
                        requester.profile_image as requester_profile_image
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user owner ON l.user_id = owner.user_id
                    JOIN user requester ON r.requester_id = requester.user_id
                ");
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add image URLs
                foreach ($requests as &$request) {
                    if (!empty($request['image_path'])) {
                        $request['book_image_url'] = "/api/books?image=" . basename($request['image_path']);
                    }
                    if (!empty($request['owner_profile_image'])) {
                        $request['owner_profile_image_url'] = "/api/users?image=" . basename($request['owner_profile_image']);
                    }
                    if (!empty($request['requester_profile_image'])) {
                        $request['requester_profile_image_url'] = "/api/users?image=" . basename($request['requester_profile_image']);
                    }
                }
                
                echo json_encode($requests);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['book_id']) || !isset($data['type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID and request type are required']);
                break;
            }
            
            // Check if book exists and is available
            $bookCheck = $pdo->prepare("
                SELECT l.*, u.FirstName, u.LastName, u.email, u.profile_image 
                FROM livre l
                JOIN user u ON l.user_id = u.user_id
                WHERE l.book_id = ?
            ");
            $bookCheck->execute([$data['book_id']]);
            $book = $bookCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$book) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
                break;
            }
            
            if ($book['availability'] !== 'available') {
                http_response_code(409);
                echo json_encode(['error' => 'Book is not available']);
                break;
            }
            
            if ($book['user_id'] == $current_user_id) {
                http_response_code(400);
                echo json_encode(['error' => 'You cannot request your own book']);
                break;
            }
            
            // Check for existing pending request
            $duplicateCheck = $pdo->prepare("
                SELECT 1 FROM requests
                WHERE book_id = ? AND requester_id = ? AND status = 'PENDING'
            ");
            $duplicateCheck->execute([$data['book_id'], $current_user_id]);
            
            if ($duplicateCheck->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'You already have a pending request for this book']);
                break;
            }
            
            // Insert new request
            $stmt = $pdo->prepare("
                INSERT INTO requests 
                (requester_id, book_id, type, status, datedeb, durée, reasonText) 
                VALUES (?, ?, ?, 'PENDING', ?, ?, ?)
            ");
        
            $stmt->execute([
                $current_user_id,
                $data['book_id'],
                $data['type'],
                $data['datedeb'] ?? null,
                $data['durée'] ?? null,
                $data['reasonText'] ?? null
            ]);
            
            // Get requester info for notification
            $requesterStmt = $pdo->prepare("
                SELECT FirstName, LastName, email, profile_image 
                FROM user 
                WHERE user_id = ?
            ");
            $requesterStmt->execute([$current_user_id]);
            $requester = $requesterStmt->fetch(PDO::FETCH_ASSOC);
            
            // Send email to the owner
            if ($book && isset($book['email'])) {
                $subject = "New Book Request";
                $message = "Hello {$book['FirstName']},\n\n";
                $message .= "{$requester['FirstName']} {$requester['LastName']} has requested your book '{$book['title']}'.\n\n";
                $message .= "Request Type: {$data['type']}\n";
                $message .= "Reason: " . ($data['reasonText'] ?? 'Not specified') . "\n";
                $message .= "Start Date: " . ($data['datedeb'] ?? 'Not specified') . "\n";
                $message .= "Duration: " . ($data['durée'] ?? 'Not specified') . " days\n\n";
                $message .= "Please log in to respond to this request.\n\n";
                $message .= "Best regards,\nBookMate Team";
                $headers = "From: no-reply@bookmate.com\r\n";
                
                mail($book['email'], $subject, $message, $headers);
            }
        
            // Return success response with full request details
            $newRequestId = $pdo->lastInsertId();
            $newRequestStmt = $pdo->prepare("
                SELECT 
                    r.*,
                    l.*,
                    owner.user_id as owner_id,
                    owner.FirstName as owner_first_name,
                    owner.LastName as owner_last_name,
                    owner.profile_image as owner_profile_image,
                    requester.user_id as requester_id,
                    requester.FirstName as requester_first_name,
                    requester.LastName as requester_last_name,
                    requester.profile_image as requester_profile_image
                FROM requests r
                JOIN livre l ON r.book_id = l.book_id
                JOIN user owner ON l.user_id = owner.user_id
                JOIN user requester ON r.requester_id = requester.user_id
                WHERE r.request_id = ?
            ");
            $newRequestStmt->execute([$newRequestId]);
            $newRequest = $newRequestStmt->fetch(PDO::FETCH_ASSOC);
            
            // Add image URLs
            if (!empty($newRequest['image_path'])) {
                $newRequest['book_image_url'] = "/api/books?image=" . basename($newRequest['image_path']);
            }
            if (!empty($newRequest['owner_profile_image'])) {
                $newRequest['owner_profile_image_url'] = "/api/users?image=" . basename($newRequest['owner_profile_image']);
            }
            if (!empty($newRequest['requester_profile_image'])) {
                $newRequest['requester_profile_image_url'] = "/api/users?image=" . basename($newRequest['requester_profile_image']);
            }
            
            http_response_code(201);
            echo json_encode([
                'message' => 'Book request submitted successfully',
                'request' => $newRequest
            ]);
            break;
            
        case 'PUT':
            $requestData = json_decode(file_get_contents("php://input"), true);
            
            if (empty($requestData['request_id']) || empty($requestData['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID and status are required']);
                break;
            }
            
            if (!in_array($requestData['status'], ['PENDING', 'ACCEPTED', 'REJECTED'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status value']);
                break;
            }
            
            // Get current request with all related data
            $checkRequest = $pdo->prepare("
                SELECT 
                    r.*,
                    l.*,
                    l.user_id as book_owner_id,
                    owner.FirstName as owner_first_name,
                    owner.LastName as owner_last_name,
                    owner.email as owner_email,
                    requester.FirstName as requester_first_name,
                    requester.LastName as requester_last_name,
                    requester.email as requester_email
                FROM requests r
                JOIN livre l ON r.book_id = l.book_id
                JOIN user owner ON l.user_id = owner.user_id
                JOIN user requester ON r.requester_id = requester.user_id
                WHERE r.request_id = ?
            ");
            $checkRequest->execute([$requestData['request_id']]);
            $currentRequest = $checkRequest->fetch(PDO::FETCH_ASSOC);
        
            if (!$currentRequest) {
                http_response_code(404);
                echo json_encode(['error' => 'Request not found']);
                break;
            }
            
            if ($current_user_role !== 'admin' && $current_user_id != $currentRequest['book_owner_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden - Only the book owner can update request status']);
                break;
            }
            
            // Update request status
            $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE request_id = ?");
            $stmt->execute([$requestData['status'], $requestData['request_id']]);
            
            if ($requestData['status'] == 'ACCEPTED') {
                $updateBook = $pdo->prepare("UPDATE livre SET availability = 'borrowed' WHERE book_id = ?");
                $updateBook->execute([$currentRequest['book_id']]);
                
                $rejectOthers = $pdo->prepare("
                    UPDATE requests 
                    SET status = 'REJECTED' 
                    WHERE book_id = ? AND request_id != ? AND status = 'PENDING'
                ");
                $rejectOthers->execute([$currentRequest['book_id'], $requestData['request_id']]);
                
                // Send notification to requester
                if (isset($currentRequest['requester_email'])) {
                    $subject = "Your Book Request Was Accepted";
                    $message = "Hello {$currentRequest['requester_first_name']},\n\n";
                    $message .= "Your request for '{$currentRequest['title']}' has been accepted by ";
                    $message .= "{$currentRequest['owner_first_name']} {$currentRequest['owner_last_name']}.\n\n";
                    $message .= "Please contact them at {$currentRequest['owner_email']} to arrange the exchange.\n\n";
                    $message .= "Best regards,\nBookMate Team";
                    $headers = "From: no-reply@bookmate.com\r\n";
                    
                    mail($currentRequest['requester_email'], $subject, $message, $headers);
                }
            }
            
            // Get updated request with all data
            $updatedRequestStmt = $pdo->prepare("
                SELECT 
                    r.*,
                    l.*,
                    owner.user_id as owner_id,
                    owner.FirstName as owner_first_name,
                    owner.LastName as owner_last_name,
                    owner.profile_image as owner_profile_image,
                    requester.user_id as requester_id,
                    requester.FirstName as requester_first_name,
                    requester.LastName as requester_last_name,
                    requester.profile_image as requester_profile_image
                FROM requests r
                JOIN livre l ON r.book_id = l.book_id
                JOIN user owner ON l.user_id = owner.user_id
                JOIN user requester ON r.requester_id = requester.user_id
                WHERE r.request_id = ?
            ");
            $updatedRequestStmt->execute([$requestData['request_id']]);
            $updatedRequest = $updatedRequestStmt->fetch(PDO::FETCH_ASSOC);
            
            // Add image URLs
            if (!empty($updatedRequest['image_path'])) {
                $updatedRequest['book_image_url'] = "/api/books?image=" . basename($updatedRequest['image_path']);
            }
            if (!empty($updatedRequest['owner_profile_image'])) {
                $updatedRequest['owner_profile_image_url'] = "/api/users?image=" . basename($updatedRequest['owner_profile_image']);
            }
            if (!empty($updatedRequest['requester_profile_image'])) {
                $updatedRequest['requester_profile_image_url'] = "/api/users?image=" . basename($updatedRequest['requester_profile_image']);
            }
        
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'message' => 'Request status updated successfully',
                    'request' => $updatedRequest
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'message' => 'No changes made',
                    'request' => $updatedRequest
                ]);
            }
            break;
            
        case 'DELETE':
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID is required']);
                break;
            }
            
            $requestId = $_GET['id'];
            
            $requestCheck = $pdo->prepare("
                SELECT 
                    r.requester_id, 
                    r.status,
                    r.book_id,
                    l.title,
                    l.user_id as book_owner_id,
                    owner.email as owner_email,
                    owner.FirstName as owner_first_name,
                    owner.LastName as owner_last_name
                FROM requests r
                JOIN livre l ON r.book_id = l.book_id
                JOIN user owner ON l.user_id = owner.user_id
                WHERE r.request_id = ?
            ");
            $requestCheck->execute([$requestId]);
            $request = $requestCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                http_response_code(404);
                echo json_encode(['error' => 'Request not found']);
                break;
            }
            
            if ($current_user_role !== 'admin' && $current_user_id != $request['requester_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden - You can only cancel your own requests']);
                break;
            }
            
            if ($request['status'] !== 'PENDING') {
                http_response_code(400);
                echo json_encode(['error' => 'Only pending requests can be canceled']);
                break;
            }
            
            // Delete request
            $stmt = $pdo->prepare("DELETE FROM requests WHERE request_id = ?");
            $stmt->execute([$requestId]);
            
            // Notify book owner if request was deleted by requester
            if ($current_user_id == $request['requester_id'] && isset($request['owner_email'])) {
                $requesterStmt = $pdo->prepare("
                    SELECT FirstName, LastName FROM user WHERE user_id = ?
                ");
                $requesterStmt->execute([$current_user_id]);
                $requester = $requesterStmt->fetch(PDO::FETCH_ASSOC);
                
                $subject = "Book Request Canceled";
                $message = "Hello {$request['owner_first_name']},\n\n";
                $message .= "{$requester['FirstName']} {$requester['LastName']} has canceled their request for '{$request['title']}'.\n\n";
                $message .= "The book is now available for other requests.\n\n";
                $message .= "Best regards,\nBookMate Team";
                $headers = "From: no-reply@bookmate.com\r\n";
                
                mail($request['owner_email'], $subject, $message, $headers);
            }
            
            echo json_encode(['message' => 'Request canceled successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>
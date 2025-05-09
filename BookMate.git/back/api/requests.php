<?php
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper function to normalize request data (for consistency with books.php)
function normalizeRequest($request) {
    return [
        'requestId' => $request['request_id'],
        'requesterId' => $request['requester_id'],
        'bookId' => $request['book_id'],
        'type' => $request['type'],
        'status' => $request['status'],
        'datedeb' => $request['datedeb'],
        'durée' => $request['durée'],
        'reasonText' => $request['reasonText'],
        'bookTitle' => $request['title'] ?? null,
        'authorName' => $request['author_name'] ?? null,
        'requesterFirstName' => $request['requester_first_name'] ?? $request['FirstName'] ?? null,
        'requesterLastName' => $request['requester_last_name'] ?? $request['LastName'] ?? null,
        'ownerFirstName' => $request['owner_first_name'] ?? $request['FirstName'] ?? null,
        'ownerLastName' => $request['owner_last_name'] ?? $request['LastName'] ?? null,
    ];
}

session_start();
error_log('Session ID in requests.php: ' . session_id());
error_log('SESSION data: ' . json_encode($_SESSION));

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}
$authenticatedUserId = $_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single request by ID
                $stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ?");
                $stmt->execute([$_GET['id']]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request) {
                    $normalizedRequest = normalizeRequest($request);
                    echo json_encode($normalizedRequest);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Request not found']);
                }
            }
            // Get requests by user ID (as requester)
            elseif (isset($_GET['user_id'])) {
                $stmt = $pdo->prepare("
                    SELECT r.*, l.title, l.author_name, u.FirstName, u.LastName 
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user u ON l.user_id = u.user_id
                    WHERE r.requester_id = ?
                ");
                $stmt->execute([$authenticatedUserId]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $normalizedRequests = array_map('normalizeRequest', $requests);
                echo json_encode($normalizedRequests);
            }
            // Get requests for books owned by the authenticated user
            elseif (isset($_GET['owner_id'])) {
                $stmt = $pdo->prepare("
                    SELECT r.*, l.title, l.author_name, u.FirstName, u.LastName 
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user u ON r.requester_id = u.user_id
                    WHERE l.user_id = ?
                ");
                $stmt->execute([$authenticatedUserId]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $normalizedRequests = array_map('normalizeRequest', $requests);
                echo json_encode($normalizedRequests);
            }
            // Get all requests (only for authenticated user, if authorized)
            else {
                $stmt = $pdo->prepare("
                    SELECT r.*, l.title, l.author_name, 
                           requester.FirstName as requester_first_name, requester.LastName as requester_last_name,
                           owner.FirstName as owner_first_name, owner.LastName as owner_last_name
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user requester ON r.requester_id = requester.user_id
                    JOIN user owner ON l.user_id = owner.user_id
                    WHERE r.requester_id = ? OR l.user_id = ?
                ");
                $stmt->execute([$authenticatedUserId, $authenticatedUserId]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $normalizedRequests = array_map('normalizeRequest', $requests);
                echo json_encode($normalizedRequests);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (
                !isset($data['bookId']) || $data['bookId'] === '' ||
                !isset($data['reasonText']) || trim($data['reasonText']) === '' ||
                !isset($data['ownerEmail']) || trim($data['ownerEmail']) === ''
            ) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                break;
            }

            // Use authenticated user ID as requester_id
            $requesterId = $authenticatedUserId;

            // Determine owner_id from book_id
            $ownerStmt = $pdo->prepare("SELECT user_id FROM livre WHERE book_id = ?");
            $ownerStmt->execute([$data['bookId']]);
            $ownerData = $ownerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$ownerData) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
                break;
            }
            $ownerId = $ownerData['user_id'];

            // Set default values for optional fields
            $type = $data['type'] ?? 'BORROW';
            $status = $data['status'] ?? 'PENDING';
            $datedeb = $data['datedeb'] ?? null;
            $durée = $data['durée'] ?? null;

            // Insert the swap request into the database
            $stmt = $pdo->prepare("
                INSERT INTO requests (requester_id, book_id, owner_id, type, status, datedeb, durée, reasonText) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $requesterId,
                $data['bookId'],
                $ownerId,
                $type,
                $status,
                $datedeb,
                $durée,
                $data['reasonText']
            ]);

            $requestId = $pdo->lastInsertId();

            // Send email to the owner
            $subject = "New Swap Request for Your Book";
            $message = "Hello,\n\nA user has requested to swap your book (ID: {$data['bookId']}).\n\n";
            $message .= "Reason: {$data['reasonText']}\n";
            $message .= "Start Date: " . ($data['datedeb'] ?? 'Not specified') . "\n";
            $message .= "Duration: " . ($data['durée'] ?? 'Not specified') . " days\n\n";
            $message .= "Please log in to BookMate to review the request.\n\nBest regards,\nBookMate Team";
            $headers = "From: no-reply@bookmate.com\r\n";

            $mailSent = @mail($data['ownerEmail'], $subject, $message, $headers);

            // Fetch the newly created request to return in the response
            $fetchStmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ?");
            $fetchStmt->execute([$requestId]);
            $newRequest = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            http_response_code(201);
            echo json_encode([
                "success" => "Swap request submitted" . ($mailSent ? " and email sent" : ", but failed to send email"),
                "request" => normalizeRequest($newRequest)
            ]);
            break;

        case 'PUT':
            $requestData = json_decode(file_get_contents("php://input"), true);
            
            if (empty($requestData['requestId']) || empty($requestData['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID and status are required']);
                break;
            }
            
            if (!in_array($requestData['status'], ['PENDING', 'ACCEPTED', 'REJECTED'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status value']);
                break;
            }
            
            $checkRequest = $pdo->prepare("
                SELECT r.*, l.user_id as book_owner_id, l.availability
                FROM requests r
                JOIN livre l ON r.book_id = l.book_id
                WHERE r.request_id = ?
            ");
            $checkRequest->execute([$requestData['requestId']]);
            $currentRequest = $checkRequest->fetch(PDO::FETCH_ASSOC);
        
            if (!$currentRequest) {
                http_response_code(404);
                echo json_encode(['error' => 'Request not found']);
                break;
            }
            
            // Verify the authenticated user is the owner or requester
            if ($currentRequest['book_owner_id'] != $authenticatedUserId && $currentRequest['requester_id'] != $authenticatedUserId) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized to update this request']);
                break;
            }
            
            $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE request_id = ?");
            $stmt->execute([$requestData['status'], $requestData['requestId']]);
            
            if ($requestData['status'] === 'ACCEPTED') {
                $updateBook = $pdo->prepare("UPDATE livre SET availability = 'borrowed' WHERE book_id = ?");
                $updateBook->execute([$currentRequest['book_id']]);
                
                $rejectOthers = $pdo->prepare("
                    UPDATE requests 
                    SET status = 'REJECTED' 
                    WHERE book_id = ? AND request_id != ? AND status = 'PENDING'
                ");
                $rejectOthers->execute([$currentRequest['book_id'], $requestData['requestId']]);
            }
        
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Request status updated successfully']);
            } else {
                http_response_code(200);
                echo json_encode(['message' => 'No changes made']);
            }
            break;

        case 'DELETE':
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID is required']);
                break;
            }
            
            $requestId = $_GET['id'];
            
            $requestCheck = $pdo->prepare("SELECT status, requester_id FROM requests WHERE request_id = ?");
            $requestCheck->execute([$requestId]);
            $request = $requestCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                http_response_code(404);
                echo json_encode(['error' => 'Request not found']);
                break;
            }
            
            if ($request['status'] !== 'PENDING' || $request['requester_id'] != $authenticatedUserId) {
                http_response_code(400);
                echo json_encode(['error' => 'Only the requester can cancel a pending request']);
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM requests WHERE request_id = ?");
            $stmt->execute([$requestId]);
            
            echo json_encode(['message' => 'Request canceled successfully']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>
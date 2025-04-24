<?php
header("Access-Control-Allow-Origin: *"); 
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';
// not necessary
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single request by ID
                $stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ?");
                $stmt->execute([$_GET['id']]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request) {
                    echo json_encode($request);
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
                $stmt->execute([$_GET['user_id']]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($requests);
            }
            // Get requests for books owned by a specific user
            elseif (isset($_GET['owner_id'])) {
                $stmt = $pdo->prepare("
                    SELECT r.*, l.title, l.author_name, u.FirstName, u.LastName 
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user u ON r.requester_id = u.user_id
                    WHERE l.user_id = ?
                ");
                $stmt->execute([$_GET['owner_id']]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($requests);
            }
            // Get all requests
            else {
                $stmt = $pdo->query("
                    SELECT r.*, l.title, l.author_name, 
                           requester.FirstName as requester_first_name, requester.LastName as requester_last_name,
                           owner.FirstName as owner_first_name, owner.LastName as owner_last_name
                    FROM requests r
                    JOIN livre l ON r.book_id = l.book_id
                    JOIN user requester ON r.requester_id = requester.user_id
                    JOIN user owner ON l.user_id = owner.user_id
                ");
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($requests);
            }
            break;


            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                if (!isset($data['book_id']) || !isset($data['reason']) || !isset($data['datedeb']) || !isset($data['durée']) || !isset($data['type'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    break;
                }
        
                $book_id = (int)$data['book_id'];
                $reason = $data['reason'];
                $datedeb = $data['datedeb'];
                $durée = (int)$data['durée'];
                $type = strtoupper($data['type']); // Convert to uppercase to match enum ('BORROW', 'EXCHANGE')
                $requester_id = 1; // Replace with actual logged-in user ID (e.g., from session or token)
        
                $stmt = $pdo->prepare("INSERT INTO requests (book_id, requester_id, reasonText, datedeb, durée, type, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
                $stmt->execute([$book_id, $requester_id, $reason, $datedeb, $durée, $type]);
        
                echo json_encode(['message' => 'Request submitted successfully']);
                break;
        
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            
        /*case 'POST':
            // Parse the incoming JSON data
            $requestData = json_decode(file_get_contents("php://input"), true);
            
            // Validate required fields
            if (empty($requestData['requester_id']) || empty($requestData['book_id']) || empty($requestData['type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Requester ID, book ID, and request type are required']);
                break;
            }
            
            // Check if user exists
            $userCheck = $pdo->prepare("SELECT 1 FROM user WHERE user_id = ?");
            $userCheck->execute([$requestData['requester_id']]);
            
            if ($userCheck->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Requester not found']);
                break;
            }
            
            // Check if book exists and is available
            $bookCheck = $pdo->prepare("SELECT user_id, availability FROM livre WHERE book_id = ?");
            $bookCheck->execute([$requestData['book_id']]);
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
            
            // Check if requester is not the book owner
            if ($book['user_id'] == $requestData['requester_id']) {
                http_response_code(400);
                echo json_encode(['error' => 'You cannot request your own book']);
                break;
            }
            
            // Check for existing pending request
            $duplicateCheck = $pdo->prepare("
                SELECT 1 FROM requests
                WHERE book_id = ? AND requester_id = ? AND status = 'PENDING'
            ");
            $duplicateCheck->execute([$requestData['book_id'], $requestData['requester_id']]);
            
            if ($duplicateCheck->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'You already have a pending request for this book']);
                break;
            }
            
            // Insert new request
            $stmt = $pdo->prepare("
                INSERT INTO requests 
                (requester_id, book_id, type, status) 
                VALUES (?, ?, ?, 'PENDING')
            ");
        
            $stmt->execute([
                $requestData['requester_id'],
                $requestData['book_id'],
                $requestData['type'] // 'BORROW' or 'EXCHANGE'
            ]);
        
            // Return success response
            http_response_code(201);
            echo json_encode([
                'id' => $pdo->lastInsertId(),
                'message' => 'Book request submitted successfully'
            ]);
            break;*/
            
        case 'PUT':
            $requestData = json_decode(file_get_contents("php://input"), true);
            
            // Verify request ID and status
            if (empty($requestData['request_id']) || empty($requestData['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID and status are required']);
                break;
            }
            
            // Validate status value
            if (!in_array($requestData['status'], ['PENDING', 'ACCEPTED', 'REJECTED'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status value']);
                break;
            }
            
            // Get current request data
            $checkRequest = $pdo->prepare("
                SELECT r.*, l.user_id as book_owner_id, l.availability
                FROM requests r
                JOIN livre l ON r.book_id = l.book_id
                WHERE r.request_id = ?
            ");
            $checkRequest->execute([$requestData['request_id']]);
            $currentRequest = $checkRequest->fetch(PDO::FETCH_ASSOC);
        
            if (!$currentRequest) {
                http_response_code(404);
                echo json_encode(['error' => 'Request not found']);
                break;
            }
            
            // Update request status
            $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE request_id = ?");
            $stmt->execute([$requestData['status'], $requestData['request_id']]);
            
            // If request is accepted, update book availability
            if ($requestData['status'] == 'ACCEPTED') {
                // Update book availability
                $updateBook = $pdo->prepare("UPDATE livre SET availability = 'borrowed' WHERE book_id = ?");
                $updateBook->execute([$currentRequest['book_id']]);
                
                // Reject all other pending requests for this book
                $rejectOthers = $pdo->prepare("
                    UPDATE requests 
                    SET status = 'REJECTED' 
                    WHERE book_id = ? AND request_id != ? AND status = 'PENDING'
                ");
                $rejectOthers->execute([$currentRequest['book_id'], $requestData['request_id']]);
            }
        
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Request status updated successfully']);
            } else {
                http_response_code(200);
                echo json_encode(['message' => 'No changes made']);
            }
            break;
            
        case 'DELETE':
            // Verify request ID
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID is required']);
                break;
            }
            
            $requestId = $_GET['id'];
            
            // Check if request exists and has pending status
            $requestCheck = $pdo->prepare("SELECT status FROM requests WHERE request_id = ?");
            $requestCheck->execute([$requestId]);
            $request = $requestCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                http_response_code(404);
                echo json_encode(['error' => 'Request not found']);
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
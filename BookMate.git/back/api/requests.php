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

// Helper function to normalize request data
function normalizeRequest($request) {
    return [
        'requestId' => $request['request_id'],
        'requesterId' => $request['requester_id'],
        'bookId' => $request['book_id'],
        'ownerId' => $request['owner_id'],
        'type' => $request['type'],
        'status' => $request['status'],
        'datedeb' => $request['datedeb'],
        'duration' => $request['duration'],
        'reasonText' => $request['reasonText'],
        'bookTitle' => $request['title'] ?? null,
        'authorName' => $request['author_name'] ?? null,
        'requesterFirstName' => $request['requester_first_name'] ?? null,
        'requesterLastName' => $request['requester_last_name'] ?? null,
        'ownerFirstName' => $request['owner_first_name'] ?? null,
        'ownerLastName' => $request['owner_last_name'] ?? null,
    ];
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single request by ID
                $stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ?");
                $stmt->execute([$_GET['id']]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
                if ($request) {
                    echo json_encode(normalizeRequest($request));
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Request not found']);
                }
            } else {
                // Logic for fetching requests based on user role
                if ($isAdmin) {
                    // Admin: Fetch all requests
                    $stmt = $pdo->query("
                        SELECT r.*, l.title, l.author_name, 
                               requester.FirstName AS requester_first_name, requester.LastName AS requester_last_name,
                               owner.FirstName AS owner_first_name, owner.LastName AS owner_last_name
                        FROM requests r
                        JOIN livre l ON r.book_id = l.book_id
                        JOIN user requester ON r.requester_id = requester.user_id
                        JOIN user owner ON l.user_id = owner.user_id
                    ");
                } else {
                    // Regular user: Fetch requests for books they own
                    $stmt = $pdo->prepare("
                        SELECT r.*, l.title, l.author_name, 
                               requester.FirstName AS requester_first_name, requester.LastName AS requester_last_name
                        FROM requests r
                        JOIN livre l ON r.book_id = l.book_id
                        JOIN user requester ON r.requester_id = requester.user_id
                        WHERE l.user_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                }
        
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(array_map('normalizeRequest', $requests));
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
        
            // Check if the user is authenticated
            $requesterId = $_SESSION['user_id'] ?? null; // Use session for requester ID
            $isAdmin = $_SESSION['role'] === 'admin'; // Assuming 'role' is stored in the session
        
            if (!$requesterId) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized: Please log in']);
                break;
            }
        
            // Check if the user is an admin
            if ($isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Admins are not allowed to create requests']);
                break;
            }
        
            // Validate required fields
            if (
                empty($data['bookId']) ||
                empty($data['reasonText']) ||
                empty($data['ownerId'])
            ) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                break;
            }
        
            // Prepare request data
            $type = $data['type'] ?? 'BORROW';
            $status = 'PENDING';
            $datedeb = $data['datedeb'] ?? null;
            $duration = $data['duration'] ?? null;
        
            try {
                // Insert the request into the database
                $stmt = $pdo->prepare("
                    INSERT INTO requests (requester_id, book_id, owner_id, type, status, datedeb, duration, reasonText) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $requesterId,
                    $data['bookId'],
                    $data['ownerId'],
                    $type,
                    $status,
                    $datedeb,
                    $duration,
                    $data['reasonText']
                ]);
        
                // Fetch the newly created request
                $requestId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ?");
                $stmt->execute([$requestId]);
                $newRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        
                // Respond with success
                http_response_code(201);
                echo json_encode([
                    'success' => 'Request created successfully',
                    'request' => normalizeRequest($newRequest)
                ]);
            } catch (PDOException $e) {
                // Handle database errors
                http_response_code(500);
                echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['requestId']) || empty($data['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID and status are required']);
                break;
            }

            if (!in_array($data['status'], ['PENDING', 'ACCEPTED', 'REJECTED'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status value']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE request_id = ?");
            $stmt->execute([$data['status'], $data['requestId']]);

            if ($data['status'] === 'ACCEPTED') {
                $stmt = $pdo->prepare("SELECT book_id FROM requests WHERE request_id = ?");
                $stmt->execute([$data['requestId']]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($request) {
                    $stmt = $pdo->prepare("UPDATE livre SET availability = 0 WHERE book_id = ?");
                    $stmt->execute([$request['book_id']]);

                    $stmt = $pdo->prepare("
                        UPDATE requests 
                        SET status = 'REJECTED' 
                        WHERE book_id = ? AND request_id != ? AND status = 'PENDING'
                    ");
                    $stmt->execute([$request['book_id'], $data['requestId']]);
                }
            }

            echo json_encode(['message' => 'Request updated successfully']);
            break;

        case 'DELETE':
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID is required']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM requests WHERE request_id = ?");
            $stmt->execute([$_GET['id']]);

            echo json_encode(['message' => 'Request deleted successfully']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
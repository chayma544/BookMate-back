<?php 

session_start();

if(!isset($_SESSION)){
    header('location:login.php');
}

header('Content-Type: application/json'); // Force JSON response
error_reporting(E_ALL); // Show all errors
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *"); 
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(response_code: 200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            //  show profile info 
            if (isset($_SESSION['user_id'])) {
                $id= $_SESSION['user_id'];
                error_log("Received user_id: " . $id); // Debug log error?
                $stmt = $pdo->prepare("SELECT * FROM user WHERE user_id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch(mode: PDO::FETCH_ASSOC);
                error_log("Fetched user: " . json_encode($user)); // Debug log
                if ($user) {
                    echo json_encode($user);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
            } 
            break;

    
            case 'PUT':
                // Verify user is logged in
                if (empty($_SESSION['user_id'])) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Unauthorized - Please log in']);
                    break;
                }
            
                $currentUserId = $_SESSION['user_id'];
                $isAdmin = ($_SESSION['role'] ?? 'user') === 'admin';
                $requestData = json_decode(file_get_contents("php://input"), true);
            
                // ADMIN FUNCTIONALITY: Block/Unblock users
                if ($isAdmin && isset($requestData['user_id']) && isset($requestData['status'])) {
                    // Validate status
                    if (!in_array($requestData['status'], ['active', 'blocked'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid status value']);
                        break;
                    }
            
                    // Prevent admins from blocking themselves
                    if ($requestData['user_id'] == $currentUserId) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Admins cannot block themselves']);
                        break;
                    }
            
                    // Update user status
                    $stmt = $pdo->prepare("UPDATE user SET status = ? WHERE user_id = ?");
                    $stmt->execute([$requestData['status'], $requestData['user_id']]);
            
                    if ($stmt->rowCount() > 0) {
                        echo json_encode([
                            'message' => 'User status updated successfully',
                            'new_status' => $requestData['status']
                        ]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'User not found or no changes made']);
                    }
                    break;
                }
            
                // REGULAR USER PROFILE UPDATE
                $allowedFields = ['FirstName', 'LastName', 'age', 'address'];
                $updateFields = [];
                $params = [];
                
                foreach ($allowedFields as $field) {
                    if (isset($requestData[$field])) {
                        $cleanValue = htmlspecialchars(trim($requestData[$field]), ENT_QUOTES, 'UTF-8');
                        $updateFields[] = "`$field` = ?";
                        $params[] = $cleanValue;
                    }
                }
            
                if (empty($updateFields)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No valid fields provided for update']);
                    break;
                }
            
                $params[] = $currentUserId; // WHERE condition
            
                try {
                    $query = "UPDATE user SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
            
                    if ($stmt->rowCount() > 0) {
                        $updatedUser = $pdo->prepare("
                            SELECT user_id, FirstName, LastName, age, address, status 
                            FROM user 
                            WHERE user_id = ?
                        ");
                        $updatedUser->execute([$currentUserId]);
                        
                        echo json_encode([
                            'message' => 'Profile updated successfully',
                            'user' => $updatedUser->fetch(PDO::FETCH_ASSOC)
                        ]);
                    } else {
                        http_response_code(200);
                        echo json_encode(['message' => 'No changes made']);
                    }
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Database error',
                        'message' => $e->getMessage()
                    ]);
                }
                break;

        case 'DELETE':

            // Verify user is logged in
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized - Please log in']);
                break;
            }

            $userId = $_SESSION['user_id'];

            try {
                // Start transaction
                $pdo->beginTransaction();

                // 1. Delete profile image if exists
                $stmt = $pdo->prepare("SELECT imageURL FROM user WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userImage = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userImage && !empty($userImage['imageURL'])) {
                    if (file_exists($userImage['imageURL'])) {
                        unlink('../user_images'.$userImage['imageURL']);
                    }
                }

                // 2. Delete all book images and their records
                $bookStmt = $pdo->prepare("SELECT book_id, URL FROM livre WHERE user_id = ?");
                $bookStmt->execute([$userId]);
                $books = $bookStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($books as $book) {
                    // Delete book image
                    if (!empty($book['URL']) && file_exists($book['URL'])) {
                        unlink('../book_images'.$book['URL']);
                    }
                    
                    // Delete all requests associated with this book
                    $pdo->prepare("DELETE FROM requests WHERE book_id = ?")->execute([$book['book_id']]);
                }
                
                // Delete all books
                $pdo->prepare("DELETE FROM livre WHERE user_id = ?")->execute([$userId]);

                // 3. Delete all requests made BY this user
                $pdo->prepare("DELETE FROM requests WHERE requester_id = ?")->execute([$userId]);

                // 5. Finally delete the user
                $deleteStmt = $pdo->prepare("DELETE FROM user WHERE user_id = ?");
                $deleteStmt->execute([$userId]);

                if ($deleteStmt->rowCount() > 0) {
                    $pdo->commit(); // Commit all changes
                    session_destroy(); // Clear the session
                    
                    echo json_encode([
                        'message' => 'Account deleted successfully',
                        'deleted_books' => count($books),
                        'deleted_requests' => $pdo->query("SELECT ROW_COUNT()")->fetchColumn()
                    ]);
                } else {
                    $pdo->rollBack();
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }

            } catch (PDOException $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode([
                    'error' => 'Database error',
                    'message' => $e->getMessage()
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode([
                    'error' => 'File system error',
                    'message' => $e->getMessage()
                ]);
            }
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

function addSwapScore($userId) {
    global $pdo;
    // Increase the user's swap score by 1 each time a swap is made
    $stmt = $pdo->prepare("UPDATE user SET user_swap_score = user_swap_score + 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

// change me 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'updatepdp') {
    $message = updatePdp($_SESSION['user_id']);
    echo $message;
}
?>
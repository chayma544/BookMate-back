<?php

// Start session to track logged-in users
session_start();

// Configuration
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB

// Set CORS headers for Angular frontend
header("Access-Control-Allow-Origin: https://localhost:4200");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Database connection
require_once __DIR__ . '/../config/db.php';

/**
 * Sanitize input data to prevent XSS attacks
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Authentication Check
 * 
 * For all non-GET requests, verify user is logged in
 */
$method = $_SERVER['REQUEST_METHOD'];
$requiresAuth = $method !== 'GET'; // Only GET requests are public

if ($requiresAuth) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please log in']);
        exit();
    }
    
    $current_user_id = $_SESSION['user_id'];

    /**
     * Rate Limiting (100 requests per minute per user)
     */
    $rateLimitKey = 'book_api_'.$current_user_id;
    $currentTime = time();
    
    if (!isset($_SESSION['rate_limits'][$rateLimitKey]) || 
        $_SESSION['rate_limits'][$rateLimitKey]['reset_time'] < $currentTime) {
        
        $_SESSION['rate_limits'][$rateLimitKey] = [
            'count' => 1,
            'reset_time' => $currentTime + 60
        ];
    } else {
        $_SESSION['rate_limits'][$rateLimitKey]['count']++;
        
        if ($_SESSION['rate_limits'][$rateLimitKey]['count'] > 100) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Too many requests',
                'retry_after' => $_SESSION['rate_limits'][$rateLimitKey]['reset_time'] - $currentTime
            ]);
            exit();
        }
    }
}

/**
 * Image Request Handler
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['image'])) {
    $imagePath = realpath(UPLOAD_DIR . sanitizeInput($_GET['image']));
    
    // Security check
    if (strpos($imagePath, realpath(UPLOAD_DIR)) !== 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    
    if (file_exists($imagePath)) {
        $mimeType = mime_content_type($imagePath);
        header('Content-Type: ' . $mimeType);
        readfile($imagePath);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
    }
    exit();
}



function normalizeBook(array $book): array {
    return [
        'id' => $book['book_id'],
        'title' => $book['title'],
        'author' => $book['author_name'],
        'language' => $book['language'],
        'genre' => $book['genre'],
        'releaseDate' => $book['release_date'],
        'status' => $book['status'],
        'coverImage' => $book['URL'],
        'availability' => (bool) $book['availability'],
        'userId' => $book['user_id']
    ];
}

try {
    switch ($method) {
        
            case 'GET':
                if (isset($_GET['own_books']) && $_GET['own_books'] === 'true') {
                    // Fetch the user's own books (for "My Library" page), hardcoded user_id = 1
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE user_id = ?");
                    $stmt->execute([1]); // Hardcoded user_id = 1
                    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $normalizedBooks = array_map('normalizeBook', $books);
                    echo json_encode($normalizedBooks);
                } 
                elseif (isset($_GET['book_id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE `book_id` = ?");
                    $stmt->execute([$_GET['book_id']]);
                    $book = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($book) {
                        $normalizedBook = normalizeBook($book);
                        echo json_encode($normalizedBook);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Book not found']);
                    }
                } 
                elseif (isset($_GET['title']) || isset($_GET['genre']) || isset($_GET['author'])) {
                    $title = isset($_GET['title']) ? "%{$_GET['title']}%" : "%";
                    $genre = isset($_GET['genre']) ? $_GET['genre'] : "%";
                    $author = isset($_GET['author']) ? "%{$_GET['author']}%" : "%";
        
                    $stmt = $pdo->prepare("
                        SELECT * FROM livre 
                        WHERE (title LIKE :title AND author_name LIKE :author
                        AND genre LIKE :genre
                        AND availability = 1)
                    ");
        
                    $stmt->execute([
                        ':title' => $title,
                        ':author' => $author,
                        ':genre' => $genre
                    ]);
        
                    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $normalizedBooks = array_map('normalizeBook', $books);
                    echo json_encode($normalizedBooks);
                } 
                else {
                    // Fetch available books, excluding user_id = 1 (for "Home" page)
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE availability = 1 AND user_id <> ?");
                    $stmt->execute([1]); // Hardcoded user_id = 1
                    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $normalizedBooks = array_map('normalizeBook', $books);
                    echo json_encode($normalizedBooks);
                }
                break;
        /*case 'POST':
            $requestData = json_decode(file_get_contents("php://input"), true);

            if (empty($requestData['title']) || empty($requestData['author_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and author name are required']);
                break;
            }

            $user_id = $current_user_id;
            $title = $requestData['title'];
            $author_name = $requestData['author_name'];

            // Check if user exists
            $userCheck = $pdo->prepare("SELECT 1 FROM user WHERE user_id = ?");
            $userCheck->execute([$user_id]);
            
            if ($userCheck->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                break;
            }

            // Check for duplicate book
            $duplicateCheck = $pdo->prepare("
                SELECT 1 FROM livre
                WHERE title = ? 
                AND author_name = ? 
                AND user_id = ?
            ");
            $duplicateCheck->execute([$title, $author_name, $user_id]);
            
            if ($duplicateCheck->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'This user already has a book with the same title and author']);
                break;
            }

            // Handle image upload
            $imagePath = null;
            if ($imageData && $imageData['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $imageData['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mimeType, $allowedTypes)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Only JPG, PNG, and GIF images are allowed']);
                    break;
                }
                
                if ($imageData['size'] > MAX_FILE_SIZE) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Image size exceeds 2MB limit']);
                    break;
                }
                
                if (!file_exists(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                
                $extension = pathinfo($imageData['name'], PATHINFO_EXTENSION);
                $filename = uniqid('book_', true) . '.' . $extension;
                $destination = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($imageData['tmp_name'], $destination)) {
                    $imagePath = 'uploads/' . $filename;
                } else {
                    error_log("Failed to move uploaded file");
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to save image']);
                    break;
                }
            }

            // Insert new book
            $stmt = $pdo->prepare("
                INSERT INTO livre 
                (title, author_name, language, genre, release_date, status, dateAjout, availability, user_id, image_path) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
            ");

            $stmt->execute([
                $requestData['title'],
                $requestData['author_name'],
                $requestData['language'] ?? 'Unknown',
                $requestData['genre'] ?? 'Other',
                $requestData['release_date'] ?? null,
                $requestData['status'] ?? 'good',
                $requestData['availability'] ?? 'available',
                $user_id,
                $imagePath
            ]);

            // Return the created book
            $newBookId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM livre WHERE book_id = ?");
            $stmt->execute([$newBookId]);
            $newBook = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($newBook['image_path']) {
                $newBook['image_url'] = "/api/books?image=" . basename($newBook['image_path']);
            }

            http_response_code(201);
            echo json_encode([
                'message' => 'Book added successfully',
                'book' => $newBook
            ]);
            break;*/



            case 'POST':
                $requestData = json_decode(file_get_contents("php://input"), true);
    
                if (empty($requestData['title']) || empty($requestData['authorName'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Title and author name are required']);
                    break;
                }
    
                $title = $requestData['title'];
                $authorName = $requestData['authorName'];
    
                // Hardcode user_id to 1
                $userId = 1;
    
                $userCheck = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
                $userCheck->execute([$userId]);
    
                if ($userCheck->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    break;
                }
    
                $duplicateCheck = $pdo->prepare("
                    SELECT 1 FROM livre
                    WHERE title = ? 
                    AND author_name = ? 
                    AND user_id = ?
                ");
                $duplicateCheck->execute([$title, $authorName, $userId]);
    
                if ($duplicateCheck->rowCount() > 0) {
                    http_response_code(409);
                    echo json_encode(['error' => 'This user already has a book with the same title and author']);
                    break;
                }
    
                // Explicitly cast availability to boolean (1 or 0)
                $availability = isset($requestData['availability']) ? (int) $requestData['availability'] : 1;
                $availability = $availability ? 1 : 0;
    
                $stmt = $pdo->prepare("
                    INSERT INTO livre 
                    (title, author_name, language, genre, release_date, status, URL, dateAjout, availability, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");
    
                $stmt->execute([
                    $requestData['title'],
                    $requestData['authorName'],
                    $requestData['language'] ?? 'Unknown',
                    $requestData['genre'] ?? 'Other',
                    $requestData['releaseDate'] ?? null,
                    $requestData['status'] ?? 'good',
                    $requestData['coverImage'] ?? '',
                    $availability,
                    $userId // Hardcoded user_id = 1
                ]);
    
                $bookId = $pdo->lastInsertId();
    
                // Fetch the newly added book to return in the response
                $fetchStmt = $pdo->prepare("SELECT * FROM livre WHERE book_id = ?");
                $fetchStmt->execute([$bookId]);
                $newBook = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    
                http_response_code(201);
                echo json_encode([
                    'book' => normalizeBook($newBook),
                    'message' => 'Book added successfully'
                ]);
                break;
    





        case 'PUT':
            $requestData = sanitizeInput(json_decode(file_get_contents("php://input"), true));
            
            if (empty($requestData['book_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }
        
            // Verify book exists and belongs to user
            $checkBook = $pdo->prepare("
                SELECT * FROM livre 
                WHERE book_id = ? AND user_id = ?
            ");
            $checkBook->execute([$requestData['book_id'], $current_user_id]);

            if ($checkBook->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found or not owned by you']);
                break;
            }

            $currentData = $checkBook->fetch(PDO::FETCH_ASSOC);
            $mergedData = array_merge($currentData, $requestData);
        
            // Prepare dynamic update
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'title', 'author_name', 'language', 'genre',
                'release_date', 'status', 'availability'
            ];
        
            foreach ($allowedFields as $field) {
                if (isset($requestData[$field])) {
                    $updateFields[] = "`$field` = ?";
                    $params[] = $requestData[$field];
                }
            }
        
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields provided for update']);
                break;
            }
        
            $params[] = $requestData['book_id'];
        
            $query = "UPDATE livre SET " . implode(', ', $updateFields) . " WHERE book_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Book updated successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No changes made']);
            }
            break;

        case 'DELETE':
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }

            // Verify ownership
            $verifyOwnership = $pdo->prepare("
                SELECT image_path FROM livre 
                WHERE book_id = ? AND user_id = ?
            ");
            $verifyOwnership->execute([$_GET['id'], $current_user_id]);
    
            if ($verifyOwnership->rowCount() === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only delete your own books']);
                break;
            }
            
            $bookData = $verifyOwnership->fetch(PDO::FETCH_ASSOC);
            $bookId = $_GET['id'];
            
            // Delete book
            $stmt = $pdo->prepare("DELETE FROM livre WHERE book_id = ?");
            $stmt->execute([$bookId]);
            
            if ($stmt->rowCount() > 0) {
                // Delete associated image
                if (!empty($bookData['image_path'])) {
                    $imagePath = realpath(UPLOAD_DIR . basename($bookData['image_path']));
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                echo json_encode(['message' => 'Book removed successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    error_log("[".date('Y-m-d H:i:s')."] Database Error: ".$e->getMessage()."\n", 3, __DIR__."/../logs/error.log");
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => (ENVIRONMENT === 'development') ? $e->getMessage() : 'Internal server error'
    ]);
} catch (Exception $e) {
    error_log("[".date('Y-m-d H:i:s')."] Error: ".$e->getMessage()."\n", 3, __DIR__."/../logs/error.log");
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => (ENVIRONMENT === 'development') ? $e->getMessage() : 'Internal server error'
    ]);
}
/**
 * Normalize a book's data.
 *
 * @param array $book The book data to normalize.
 * @return array The normalized book data.
 */


?>

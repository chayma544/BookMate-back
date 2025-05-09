<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once '../config/db.php'; // Include your database connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

function updatebp($user_id) {
    global $pdo;
        // simple , amloulha api endpoint wahadha mathabik 
        //echo  updatePdp($_SESSION['user_id']); fel api eli ybadel taswira 
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $imageName = basename($_FILES['image']['name']);
        $uploadDir = 'book_images/';
        $targetPath = $uploadDir . $imageName;

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['image']['tmp_name']);

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                try {
                    $stmt = $pdo->prepare('UPDATE books SET URL = :imageURL WHERE user_id = :user_id');
                    $stmt->execute([
                        'imageURL' => $targetPath,
                        'user_id' => $user_id
                    ]);
                    return "Image ajoutée avec succès.";
                } catch (Exception $e) {
                    return "Erreur lors de l'ajout : " . $e->getMessage();
                }
            } else {
                return "Échec du téléchargement de l'image.";
            }
        } else {
            return "Type d'image non autorisé (JPG, PNG, GIF uniquement).";
        }
    } else {
        return "Veuillez sélectionner une image.";
    }
}

?>
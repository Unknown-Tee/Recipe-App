<?php
require 'conf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$recipe_id = intval($_GET['id'] ?? 0);
if (!$recipe_id) {
    header("Location: recipes.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get recipe details with related data
$query = "SELECT r.*, c.name as category_name, u.fullname as author_name,
          (SELECT AVG(rating) FROM tbl_recipe_reviews WHERE recipe_id = r.id) as avg_rating,
          (SELECT COUNT(*) FROM tbl_recipe_reviews WHERE recipe_id = r.id) as review_count,
          (SELECT COUNT(*) FROM tbl_user_favorites WHERE user_id = ? AND recipe_id = r.id) as is_favorited
          FROM tbl_recipes r 
          LEFT JOIN tbl_categories c ON r.category_id = c.id 
          LEFT JOIN tbl_users u ON r.user_id = u.id 
          WHERE r.id = ? AND (r.is_public = 1 OR r.user_id = ?)";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $recipe_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!($recipe = $result->fetch_assoc())) {
    header("Location: recipes.php");
    exit;
}

// Get ingredients
$ingredients_query = "SELECT * FROM tbl_ingredients WHERE recipe_id = ? ORDER BY order_index";
$stmt = $conn->prepare($ingredients_query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$ingredients = $stmt->get_result();

// Get reviews
$reviews_query = "SELECT r.*, u.fullname as reviewer_name FROM tbl_recipe_reviews r 
                  LEFT JOIN tbl_users u ON r.user_id = u.id 
                  WHERE r.recipe_id = ? ORDER BY r.created_at DESC LIMIT 10";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$reviews = $stmt->get_result();

// Check if current user has reviewed this recipe
$user_review_query = "SELECT * FROM tbl_recipe_reviews WHERE recipe_id = ? AND user_id = ?";
$stmt = $conn->prepare($user_review_query);
$stmt->bind_param("ii", $recipe_id, $user_id);
$stmt->execute();
$user_review = $stmt->get_result()->fetch_assoc();

// Handle review submission
$review_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_review'])) {
    $rating = intval($_POST['rating']);
    $review_text = trim($_POST['review_text']);
    
    if ($rating >= 1 && $rating <= 5) {
        if ($user_review) {
            // Update existing review
            $stmt = $conn->prepare("UPDATE tbl_recipe_reviews SET rating = ?, review_text = ? WHERE recipe_id = ? AND user_id = ?");
            $stmt->bind_param("isii", $rating, $review_text, $recipe_id, $user_id);
        } else {
            // Insert new review
            $stmt = $conn->prepare("INSERT INTO tbl_recipe_reviews (recipe_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $recipe_id, $user_id, $rating, $review_text);
        }
        
        if ($stmt->execute()) {
            $review_msg = "Review submitted successfully!";
            // Refresh page to show updated review
            header("Location: view_recipe.php?id=$recipe_id");
            exit;
        } else {
            $review_msg = "Error submitting review.";
        }
    } else {
        $review_msg = "Please provide a valid rating (1-5 stars).";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($recipe['title']) ?> - Recipe Book</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        
        .recipe-header {
            position: relative;
            height: 400px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
        }
        
        .recipe-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .recipe-header-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 40px;
            color: white;
        }
        
        .recipe-title {
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .recipe-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 1.1em;
        }
        
        .recipe-meta span {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .recipe-content {
            padding: 40px;
        }
        
        .recipe-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .favorite-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.2s ease;
            padding: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        
        .favorite-btn:hover {
            transform: scale(1.2);
        }
        
        .favorite-btn.active {
            color: #e74c3c;
        }
        
        .recipe-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .ingredients-section, .instructions-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
        }
        
        .ingredients-section h3, .instructions-section h3 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5em;
        }
        
        .ingredient-list {
            list-style: none;
        }
        
        .ingredient-list li {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ingredient-list li:last-child {
            border-bottom: none;
        }
        
        .ingredient-name {
            font-weight: 500;
            color: #333;
        }
        
        .ingredient-quantity {
            color: #666;
            font-size: 0.9em;
            background: white;
            padding: 4px 12px;
            border-radius: 15px;
        }
        
        .instructions-text {
            line-height: 1.8;
            color: #333;
            white-space: pre-line;
        }
        
        .reviews-section {
            margin-top: 40px;
        }
        
        .reviews-section h3 {
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5em;
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stars {
            color: #ffc107;
            font-size: 1.5em;
        }
        
        .rating-text {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }
        
        .review-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .review-form h4 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .rating-input {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .star-input {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .star-input:hover, .star-input.active {
            color: #ffc107;
        }
        
        .review-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 15px;
        }
        
        .review-form textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .review-item {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .reviewer-name {
            font-weight: bold;
            color: #333;
        }
        
        .review-date {
            color: #666;
            font-size: 0.9em;
        }
        
        .review-rating {
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        .review-text {
            color: #666;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .recipe-grid {
                grid-template-columns: 1fr;
            }
            
            .recipe-title {
                font-size: 2em;
            }
            
            .recipe-meta {
                font-size: 1em;
            }
            
            .recipe-content {
                padding: 20px;
            }
            
            .recipe-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="recipe-header">
            <?php if ($recipe['image_url']): ?>
                <img src="<?= htmlspecialchars($recipe['image_url']) ?>" alt="<?= htmlspecialchars($recipe['title']) ?>">
            <?php else: ?>
                <div style="font-size: 100px;">🍽️</div>
            <?php endif; ?>
            
            <div class="recipe-header-overlay">
                <div class="recipe-title"><?= htmlspecialchars($recipe['title']) ?></div>
                <div class="recipe-meta">
                    <?php if ($recipe['category_name']): ?>
                        <span><?= htmlspecialchars($recipe['category_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($recipe['country']): ?>
                        <span><?= htmlspecialchars($recipe['country']) ?></span>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($recipe['difficulty']) ?></span>
                    <?php if ($recipe['prep_time'] || $recipe['cook_time']): ?>
                        <span><?= ($recipe['prep_time'] + $recipe['cook_time']) ?> min</span>
                    <?php endif; ?>
                    <span><?= $recipe['servings'] ?> servings</span>
                </div>
            </div>
        </div>
        
        <div class="recipe-content">
            <div class="recipe-actions">
                <button class="favorite-btn <?= $recipe['is_favorited'] ? 'active' : '' ?>" 
                        onclick="toggleFavorite(<?= $recipe['id'] ?>, this)">
                    <?= $recipe['is_favorited'] ? '❤️' : '🤍' ?>
                </button>
                
                <?php if ($recipe['user_id'] == $user_id): ?>
                    <a href="edit_recipe.php?id=<?= $recipe['id'] ?>" class="btn btn-secondary">✏️ Edit Recipe</a>
                <?php endif; ?>
                
                <a href="recipes.php" class="btn btn-secondary">← Back to Recipes</a>
            </div>
            
            <?php if ($recipe['description']): ?>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
                    <p style="color: #666; font-size: 1.1em; line-height: 1.6; font-style: italic;">
                        <?= htmlspecialchars($recipe['description']) ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="recipe-grid">
                <div class="ingredients-section">
                    <h3>🥕 Ingredients</h3>
                    <ul class="ingredient-list">
                        <?php while ($ingredient = $ingredients->fetch_assoc()): ?>
                            <li>
                                <span class="ingredient-name"><?= htmlspecialchars($ingredient['ingredient_name']) ?></span>
                                <?php if ($ingredient['quantity']): ?>
                                    <span class="ingredient-quantity"><?= htmlspecialchars($ingredient['quantity']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                
                <div class="instructions-section">
                    <h3>👨‍🍳 Instructions</h3>
                    <div class="instructions-text">
                        <?= htmlspecialchars($recipe['instructions']) ?>
                    </div>
                </div>
            </div>
            
            <div class="reviews-section">
                <h3>⭐ Reviews & Ratings</h3>
                
                <div class="rating-summary">
                    <div class="rating-display">
                        <div class="stars">
                            <?php
                            $avg_rating = round($recipe['avg_rating'] ?? 0);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $avg_rating ? '★' : '☆';
                            }
                            ?>
                        </div>
                        <div class="rating-text">
                            <?= number_format($recipe['avg_rating'] ?? 0, 1) ?>/5.0
                        </div>
                    </div>
                    <div style="color
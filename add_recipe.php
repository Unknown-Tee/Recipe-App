<?php
require 'conf.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);
    $prep_time = intval($_POST['prep_time']);
    $cook_time = intval($_POST['cook_time']);
    $servings = intval($_POST['servings']);
    $difficulty = $_POST['difficulty'];
    $category_id = intval($_POST['category_id']) ?: null;
    $country = trim($_POST['country']);
    $image_url = trim($_POST['image_url']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Get ingredients
    $ingredients = [];
    if (isset($_POST['ingredient_name'])) {
        for ($i = 0; $i < count($_POST['ingredient_name']); $i++) {
            $name = trim($_POST['ingredient_name'][$i]);
            $quantity = trim($_POST['ingredient_quantity'][$i]);
            if ($name) {
                $ingredients[] = ['name' => $name, 'quantity' => $quantity, 'order' => $i + 1];
            }
        }
    }
    
    if (empty($title) || empty($instructions) || empty($ingredients)) {
        $msg = "Please fill in title, instructions, and at least one ingredient.";
    } else {
        $conn->begin_transaction();
        
        try {
            // Insert recipe
            $stmt = $conn->prepare("INSERT INTO tbl_recipes (title, description, instructions, prep_time, cook_time, servings, difficulty, category_id, user_id, image_url, country, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiissiissi", $title, $description, $instructions, $prep_time, $cook_time, $servings, $difficulty, $category_id, $user_id, $image_url, $country, $is_public);
            $stmt->execute();
            
            $recipe_id = $conn->insert_id;
            
            // Insert ingredients
            $stmt = $conn->prepare("INSERT INTO tbl_ingredients (recipe_id, ingredient_name, quantity, order_index) VALUES (?, ?, ?, ?)");
            foreach ($ingredients as $ingredient) {
                $stmt->bind_param("issi", $recipe_id, $ingredient['name'], $ingredient['quantity'], $ingredient['order']);
                $stmt->execute();
            }
            
            $conn->commit();
            $success = true;
            $msg = "Recipe added successfully! <a href='view_recipe.php?id=$recipe_id'>View your recipe</a> or <a href='recipes.php'>browse all recipes</a>";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error adding recipe: " . $e->getMessage();
        }
    }
}

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM tbl_categories ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Recipe</title>
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
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #333;
            font-size: 2.5em;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: